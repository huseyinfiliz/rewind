<?php

namespace HuseyinFiliz\Rewind\Event;

class TemplateChanged
{
    public function __construct(
        public string $id,
        public string $operation,
    ) {
    }
}
