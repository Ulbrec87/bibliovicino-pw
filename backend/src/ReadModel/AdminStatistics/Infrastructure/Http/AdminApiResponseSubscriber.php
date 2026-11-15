<?php

declare(strict_types=1);

namespace App\ReadModel\AdminStatistics\Infrastructure\Http;

use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class AdminApiResponseSubscriber
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/admin/')) {
            return;
        }
        $response = $event->getResponse();
        $response->headers->set('Cache-Control', 'private, no-store');
        if (Response::HTTP_FORBIDDEN === $response->getStatusCode()) {
            $event->setResponse(ApiResponse::error('access_denied', 'Administrator access is required.', Response::HTTP_FORBIDDEN, ['Cache-Control' => 'private, no-store']));
        }
    }
}
