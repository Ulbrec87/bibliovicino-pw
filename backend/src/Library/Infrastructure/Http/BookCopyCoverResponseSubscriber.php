<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Http;

use App\Shared\Infrastructure\Http\ApiResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class BookCopyCoverResponseSubscriber
{
    public function __construct(private LoggerInterface $logger) {}

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: -10)]
    public function onException(ExceptionEvent $event): void
    {
        if (!$this->isCoverPath($event->getRequest()->getPathInfo())) return;
        // Gli errori di filesystem restano nei log
        $this->logger->error('Cover operation failed.', ['exception' => $event->getThrowable()]);
        $event->setResponse(ApiResponse::error('cover_operation_failed', 'Non è stato possibile completare la gestione della copertina.', 500));
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$this->isCoverPath($event->getRequest()->getPathInfo())) return;
        $event->getResponse()->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $event->getResponse()->headers->set('X-Content-Type-Options', 'nosniff');
    }

    private function isCoverPath(string $path): bool
    {
        return 1 === preg_match('~^/api/library/book-copies/[^/]+/cover(?:/|$)~', $path);
    }
}
