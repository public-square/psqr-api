<?php

namespace PublicSquare\MessageHandler\Event;

use Psr\Log\LoggerInterface;
use PublicSquare\Message\Event\PutContentEvent;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class SavePutContent implements MessageHandlerInterface
{
    public function __construct(private LoggerInterface $logger, private KernelInterface $kernel)
    {
    }

    public function __invoke(PutContentEvent $event)
    {
        $path = $this->kernel->getProjectDir() . '/' . $_ENV['CONTENT_OUTPUT_DIR'] . '/';
        $ts   = $event->getTimestamp();

        $content = json_encode([
            'timestamp' => $ts,
            'infoHash'  => $event->getInfoHash(),
            'broadcast' => $event->getBroadcast(),
        ]);

        $this->logger->info('Saving event: ' . $content);

        $files = [
            $path . 'latest.jsonl',
            $path . date('Y-m-d-H-i-s', $ts) . '.jsonl',
            $path . date('Y-m-d-H-i', $ts) . '.jsonl',
            $path . date('Y-m-d-H', $ts) . '.jsonl',
            $path . date('Y-m-d', $ts) . '.jsonl',
        ];

        foreach ($files as $f) {
            $fp = fopen($f, 'a');
            fwrite($fp, $content . PHP_EOL);
            fclose($fp);
        }

        $this->logger->info('Content saved');
    }
}
