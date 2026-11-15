<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class AuthApiExceptionSubscriber
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/auth/')) {
            return;
        }

        [$status, $code, $message] = match (true) {
            $event->getThrowable() instanceof JsonException => [Response::HTTP_BAD_REQUEST, 'invalid_json', 'The request body must be valid JSON.'],
            $event->getThrowable() instanceof BadRequestHttpException => [Response::HTTP_BAD_REQUEST, 'invalid_json', 'The request body must be valid JSON.'],
            $event->getThrowable() instanceof MethodNotAllowedHttpException => [Response::HTTP_METHOD_NOT_ALLOWED, 'method_not_allowed', 'The HTTP method is not allowed.'],
            $event->getThrowable() instanceof NotFoundHttpException => [Response::HTTP_NOT_FOUND, 'not_found', 'The requested endpoint was not found.'],
            default => [null, null, null],
        };

        if (null === $status) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => ['code' => $code, 'message' => $message],
        ], $status));
    }
}
