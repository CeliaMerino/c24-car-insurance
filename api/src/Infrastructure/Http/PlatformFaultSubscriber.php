<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

/**
 * Platform faults only at ERROR (specs/07-observability.md section 4).
 * Partner failures never reach here — the gateway maps them to outcomes.
 */
final class PlatformFaultSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', -128]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        if (!$this->isPlatformFault($throwable)) {
            return;
        }

        $comparisonId = $event->getRequest()->attributes->get('comparison_id');
        if (!is_string($comparisonId) || '' === $comparisonId) {
            $comparisonId = 'unknown';
        }

        $this->logger->error('platform.fault', [
            'comparison_id' => $comparisonId,
            'exception' => $throwable::class,
            'message' => $throwable->getMessage(),
            'stack_trace' => $throwable->getTraceAsString(),
        ]);
    }

    private function isPlatformFault(Throwable $throwable): bool
    {
        return !$throwable instanceof ValidationException;
    }
}
