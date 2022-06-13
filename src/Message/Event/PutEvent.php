<?php

namespace PublicSquare\Message\Event;

class PutEvent
{
    public const FEED_TYPE    = 1;
    public const CONTENT_TYPE = 2;
    protected int $type;

    public function __construct(private $broadcast, private string $infoHash, private int $timestamp)
    {
    }

    public function getBroadcast()
    {
        return $this->broadcast;
    }

    public function getInfoHash(): string
    {
        return $this->infoHash;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getType(): int
    {
        return $this->type;
    }
}
