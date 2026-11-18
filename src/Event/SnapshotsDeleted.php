<?php

namespace HuseyinFiliz\Rewind\Event;

class SnapshotsDeleted
{
    public function __construct(
        public int $year,
        public ?int $groupId,
        public int $count,
    ) {
    }
}
