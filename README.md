## Intervals De-Duper

I recently started syncing activities to Intervals through Wahoo rather than Strava, in response to Strava's bizarre new API policies.

For some reason, this duplicated some of my activities, especially those created through my Wahoo head unit, in some cases several times.

This is a simple script that loops through activities, groups them by name and start date, and finds groups with multiple activities sharing those properties. It then does a sanity check on the activities being genuine duplicates, and if so, deletes all but one of them.

## Usage

Create a `.env` using the `.env.example` example file and populate.

run `php public/index.php`. **There's no dry mode**, so comment out the `delete` if you'd prefer to test first!
