<?php

use David\IntervalsDedupe\Activity;
use GuzzleHttp\RequestOptions;

error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

//$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
//$twig = new \Twig\Environment($loader);

$apiKey = $_ENV['API_KEY'];

$handler = \GuzzleHttp\HandlerStack::create();
$client = new \GuzzleHttp\Client([
    'base_uri' => 'https://intervals.icu',
    'auth' => ['API_KEY', $apiKey]
]);

$athleteId = $_ENV['ATHLETE_ID'];

$oldest = \Cake\Chronos\Chronos::create(2024, 1, 1, 0, 0, 0);
$newest = $oldest->addDays(10);
$newest = \Cake\Chronos\Chronos::now();

//$oldest = \Cake\Chronos\Chronos::createFromFormat('Y-m-d', '2024-09-18');
//$newest = \Cake\Chronos\Chronos::createFromFormat('Y-m-d', '2024-09-18');

$json = $client->get(
    "/api/v1/athlete/{$athleteId}/activities",
    [RequestOptions::QUERY => ['oldest' => $oldest->format('Y-m-d'), 'newest' => $newest->format('Y-m-d')]]
)->getBody();

$rawData = json_decode($json, true);

try {
    /** @var Activity[] $activities */
    $activities = (new \CuyZ\Valinor\MapperBuilder())
        ->allowSuperfluousKeys()
        ->mapper()
        ->map(Activity::class . '[]', \CuyZ\Valinor\Mapper\Source\Source::json($json)->camelCaseKeys());

    /** @var Activity[][] $keyedActivities */
    $keyedActivities = [];
    foreach ($activities as $activity) {
        $key = $activity->name . $activity->startDate->format('Y-m-d H:i:s');

        $rawActivityData = array_values(array_filter($rawData, fn($actData) => $actData['id'] === $activity->id));
        if (count($rawActivityData) !== 1) {
            throw new Exception('Failed to find exactly one activity');
        }

        $keyedActivities[$key][] = [
            $activity,
            $rawActivityData[0]
        ];
    }

    /** @var Activity[][] $toDelete */
    $toDelete = [];
    foreach ($keyedActivities as $key => $groupedActivities) {
        if (count($groupedActivities) <= 1) {
            continue;
        }

        echo "Suspected duplicates: \n";

        $hashes = [];
        $unsetKeys = ['id', 'created', 'icu_sync_date', 'analyzed'];
        foreach ($groupedActivities as [$activity, $rawActivityData]) {
            foreach ($unsetKeys as $key) {
                unset($rawActivityData[$key]);
            }
            $hash = md5(json_encode($rawActivityData));
            $hashes[$hash] = $hash;

            echo "\t- {$activity->id} {$activity->source} {$activity->name} {$activity->created->format('c')} hash: {$hash}\n";
        }

        if (count($hashes) === 1) {
            $toDelete[] = array_map(fn(array $group) => $group[0], $groupedActivities);
        }

        echo "\n";
    }

    foreach ($toDelete as $groupedActivities) {
        usort(
            $groupedActivities,
            fn(Activity $a, Activity $b) => $a->created->getTimestamp() <=> $b->created->getTimestamp()
        );

        $keep = array_pop($groupedActivities);

        echo "Keeping {$keep->id} - {$keep->name}\n";
        foreach ($groupedActivities as $activity) {
            echo "Deleting {$activity->id} - {$activity->name}\n";

            $response = $client->delete("/api/v1/activity/{$activity->id}")->getBody()->getContents();
            echo $response . PHP_EOL;
        }
    }

//    var_dump($activities);
} catch (\CuyZ\Valinor\Mapper\MappingError $error) {
    echo $error->getMessage() . PHP_EOL;

    $messages = \CuyZ\Valinor\Mapper\Tree\Message\Messages::flattenFromNode(
        $error->node()
    );

    foreach ($messages as $message) {
        echo "$message\n";
    }
}

