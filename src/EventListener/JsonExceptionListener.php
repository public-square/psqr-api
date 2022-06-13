<?php

namespace PublicSquare\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class JsonExceptionListener
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * Allow Exceptions to be thrown with a json encoded message in the following form:
     * throw new \Exception(json_encode([
     *  ...
     * ]).
     */
    public function onKernelException(ExceptionEvent $event)
    {
        if ($_ENV['APP_ENV'] === 'dev') {
            return;
        }

        $responseData = json_decode($event->getThrowable()->getMessage(), true);

        // check to see if json is improper or is null
        if (null === $responseData || json_last_error() !== JSON_ERROR_NONE) {
            return;
        }

        $code = $event->getThrowable()->getCode();

        $event->setResponse(new JsonResponse($responseData, $code));

        // verify if prod appends to the log
        $this->logger->error($event->getThrowable()->getMessage());
    }
}
