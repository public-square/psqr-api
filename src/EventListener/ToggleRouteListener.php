<?php

namespace PublicSquare\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

final class ToggleRouteListener
{
    public function onKernelController(ControllerEvent $event)
    {
        // subRequests exist, only deny master requests
        if (!$event->isMainRequest()) {
            return;
        }

        // get current controller
        $currentController = $event->getController();

        // not valid if not array = no controller
        if (!\is_array($currentController)) {
            return;
        }

        // load api permissions file
        $apiToggle = '/../../config/packages/' . $_ENV['APP_ENV'] . '/api_toggle.json';

        $config = json_decode(file_get_contents(__DIR__ . $apiToggle), true);

        // if api permissions is not set, continue
        if (!isset($config['api_permissions'])) {
            return;
        }

        // get method
        $currentMethod = $event->getRequest()->getMethod();

        // iterate over all configs
        foreach ($config['api_permissions'] as $controller => $controllerConfig) {
            // checks if current controller matches same class name as config
            if (is_a($currentController[0], $controller) !== false) {
                // if full key is set to true
                if ($controllerConfig === true) {
                    return;
                }

                // if full key is set to false
                if ($controllerConfig === false) {
                    // hijacking controller to return an error
                    $event->setController(
                        function () {
                            return new JsonResponse([
                                'success' => false,
                                'message' => 'Permission Denied.',
                            ], 400);
                        }
                    );

                    return;
                }

                // if key is broken up by method
                if ($controllerConfig[$currentMethod] === false) {
                    // hijacking controller to return an error
                    $event->setController(
                        function () {
                            return new JsonResponse([
                                'success' => false,
                                'message' => 'Permission Denied.',
                            ], 400);
                        }
                    );

                    return;
                }
            }
        }
    }
}
