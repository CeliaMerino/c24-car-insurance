<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Comparisons contain personal input and are never cacheable
 * (specs/05-api-contract.md section 1). Applied to every response so health,
 * metrics, and error bodies cannot drift from that rule.
 */
final class NoStoreCacheSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $event->getResponse()->headers->addCacheControlDirective('no-store');
    }
}
