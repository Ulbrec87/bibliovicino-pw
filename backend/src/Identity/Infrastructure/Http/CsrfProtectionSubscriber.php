<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 16)]
final readonly class CsrfProtectionSubscriber
{
    private const array UNSAFE_METHODS = [Request::METHOD_POST, Request::METHOD_PUT, Request::METHOD_PATCH, Request::METHOD_DELETE];

    public function __construct(private CsrfTokenManagerInterface $csrfTokens)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (
            !$event->isMainRequest()
            || ('/api' !== $request->getPathInfo() && !str_starts_with($request->getPathInfo(), '/api/'))
            || !in_array($request->getMethod(), self::UNSAFE_METHODS, true)
        ) {
            return;
        }

        $submittedToken = $request->headers->get('X-CSRF-Token');

        if (!is_string($submittedToken) || !$this->csrfTokens->isTokenValid(new CsrfToken('api', $submittedToken))) {
            $event->setResponse(new JsonResponse([
                'error' => [
                    'code' => 'invalid_csrf_token',
                    'message' => 'A valid CSRF token is required.',
                ],
            ], Response::HTTP_FORBIDDEN));
        }
    }
}
