<?php

namespace PublicSquare\MessageHandler\Event;

use Psr\Log\LoggerInterface;
use PublicSquare\Message\Event\PutFeedEvent;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class SavePutFeed implements MessageHandlerInterface
{
    public function __construct(private LoggerInterface $logger, private KernelInterface $kernel)
    {
    }

    public function __invoke(PutFeedEvent $event)
    {
        $path = $this->kernel->getProjectDir() . '/' . $_ENV['FEED_OUTPUT_DIR'] . '/feed/';
        $ts   = $event->getTimestamp();

        $content = json_encode([
            'timestamp' => $ts,
            'infoHash'  => $event->getInfoHash(),
            'broadcast' => $event->getBroadcast(),
        ]);

        $this->logger->info('Saving event: ' . $content);

        $files = [
            $path . $event->getInfoHash() . '-latest.jsonl',
            $path . $event->getInfoHash() . '-' . date('Y-m-d-H-i', $ts) . '.jsonl',
            $path . $event->getInfoHash() . '-' . date('Y-m-d-H', $ts) . '.jsonl',
            $path . $event->getInfoHash() . '-' . date('Y-m-d', $ts) . '.jsonl',
        ];

        foreach ($files as $f) {
            $fp = fopen($f, 'a');
            fwrite($fp, $content . PHP_EOL);
            fclose($fp);
        }

        $this->logger->info('Feed content saved');
    }
}
