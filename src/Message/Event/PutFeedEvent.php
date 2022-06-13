<?php

namespace PublicSquare\Message\Event;

class PutFeedEvent extends PutEvent
{
    public function __construct(...$params)
    {
        $this->type = PutEvent::FEED_TYPE;

        parent::__construct(...$params);
    }
}
