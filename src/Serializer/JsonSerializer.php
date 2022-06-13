<?php

namespace PublicSquare\Serializer;

use Psr\Log\LoggerInterface;
use PublicSquare\Message\Event\PutContentEvent;
use PublicSquare\Message\Event\PutEvent;
use PublicSquare\Message\Event\PutFeedEvent;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class JsonSerializer implements SerializerInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * convert data returned from redis into a messenger envelope object.
     *
     * @param array $encodedEnvelope envelope returned from redis
     *
     * @return Envelope messenger envelope object
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        $body    = $encodedEnvelope['body'];
        $headers = $encodedEnvelope['headers'];
        $data    = match ($headers['event_type'] ?? null) {
            PutEvent::FEED_TYPE    => new PutFeedEvent($body, $headers['hash'], (int) $headers['timestamp']),
            PutEvent::CONTENT_TYPE => new PutContentEvent($body, $headers['hash'], (int) $headers['timestamp']),
            default                => new PutEvent($body, $headers['hash'], (int) $headers['timestamp']),
        };

        return new Envelope($data);
    }

    /**
     * encode messenger Envelope object into an array to be placed in redis.
     *
     * this particular encoded sets the 'type' => 'raw' so that the raw json data from the message
     * is placed in the redis stream without any encoding or surrounding properties
     *
     * @param Envelope $envelope messenger envelope object to be placed in redis
     *
     * @return array encoded envelope array to be placed in redis
     */
    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();

        $data = $message->getBroadcast();
        $hash = $message->getInfoHash();

        $response = [
            'body'    => $data,
            'headers' => [
                'type'       => 'raw',
                'hash'       => $hash,
                'event_type' => $message->getType(),
                'timestamp'  => $message->getTimestamp(),
            ],
        ];

        $this->logger->info('Encoding Message: ', $response);

        return $response;
    }
}
