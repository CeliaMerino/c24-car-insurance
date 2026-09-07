<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Port\MetricsRecorder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Counts every finished HTTP response for c24_http_responses_total
 * (specs/07-observability.md section 3.1).
 */
final class HttpResponseMetricsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MetricsRecorder $metrics,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => 'onTerminate'];
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        if (!is_string($route) || '' === $route) {
            $route = 'unmatched';
        }

        $this->metrics->recordHttpResponse($route, $event->getResponse()->getStatusCode());
    }
}
