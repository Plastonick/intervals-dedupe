<?php

declare(strict_types=1);

namespace David\IntervalsDedupe;

use DateTimeInterface;

final readonly class Activity
{
    public function __construct(
        public string $id,
        public ?string $type,
        public ?string $source,
        public ?string $description,
        public string $name,
        public DateTimeInterface $startDate,
        public DateTimeInterface $created,
        public ?string $powerMeter,
    ) {
    }
}
