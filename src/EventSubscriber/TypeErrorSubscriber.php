<?php

namespace PublicSquare\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class TypeErrorSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [
                ['typeErrorException', 10],
            ],
        ];
    }

    public function typeErrorException(ExceptionEvent $event)
    {
        $throwable = $event->getThrowable();

        if (is_a($throwable, \TypeError::class) === false) {
            // this is not a TypeError
            return;
        }

        $request = $event->getRequest();

        $jsonResponse = new JsonResponse([
            'apiTarget' => $request->getPathInfo(),
            'httpVerb'  => $request->getMethod(),
            'success'   => false,
            'message'   => 'TypeError encountered. Please verify your inputs are correct.',
            'error'     => $throwable->getMessage(),
        ], 400);

        $event->setResponse($jsonResponse);
    }
}
