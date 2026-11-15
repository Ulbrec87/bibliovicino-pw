<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Http;

use App\Library\Application\BookCopyManagement\BookCopyNotFound;
use App\Library\Application\CreateBookCopy\DiscoveryLocationRequired;
use App\Shared\Infrastructure\Http\ApiResponse;
use DomainException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class BookCopyManagementExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 10]];
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/library/book-copies/')) {
            return;
        }
        $exception = $event->getThrowable();
        $response = match (true) {
            $exception instanceof BookCopyNotFound => ApiResponse::error('book_copy_not_found', 'Book copy not available.', 404),
            $exception instanceof DiscoveryLocationRequired => ApiResponse::error('discovery_location_required', 'Configure an approximate zone before publishing.', 409),
            $exception instanceof DomainException => ApiResponse::error('book_copy_conflict', 'The operation is not allowed in the current state.', 409),
            default => null,
        };
        if (null !== $response) {
            $response->headers->set('Cache-Control', 'private, no-store');
            $event->setResponse($response);
        }
    }
}
