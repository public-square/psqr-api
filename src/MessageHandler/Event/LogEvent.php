<?php

namespace PublicSquare\MessageHandler\Event;

use Psr\Log\LoggerInterface;
use PublicSquare\Message\Event\PutEvent;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class LogEvent implements MessageHandlerInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(PutEvent $event)
    {
        $this->logger->info('Event handled: ', ['event' => $event]);
    }
}
