<?php

namespace HuseyinFiliz\Rewind\Event;

use HuseyinFiliz\Rewind\Model\CommunitySnapshot;
use HuseyinFiliz\Rewind\Model\RewindSnapshot;

class SnapshotGenerated
{
    public function __construct(
        public RewindSnapshot|CommunitySnapshot $snapshot,
        public bool $regenerated,
    ) {
    }
}
