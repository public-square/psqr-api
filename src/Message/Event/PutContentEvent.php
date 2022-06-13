<?php

namespace PublicSquare\Message\Event;

class PutContentEvent extends PutEvent
{
    public function __construct(...$params)
    {
        $this->type = PutEvent::CONTENT_TYPE;

        parent::__construct(...$params);
    }
}
