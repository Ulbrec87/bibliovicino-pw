<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use App\Identity\Infrastructure\Security\ApiAuthenticationEntryPoint;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final readonly class SessionInactivitySubscriber
{
    public function __construct(
        private Security $security,
        private TokenStorageInterface $tokenStorage,
        private ApiAuthenticationEntryPoint $authenticationEntryPoint,
        private int $inactivityTimeoutSeconds,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (
            !$event->isMainRequest()
            || ('/api' !== $request->getPathInfo() && !str_starts_with($request->getPathInfo(), '/api/'))
            || !$request->hasPreviousSession()
            || null === $this->security->getUser()
        ) {
            return;
        }

        $session = $request->getSession();
        $lastUsed = $session->getMetadataBag()->getLastUsed();

        if (time() - $lastUsed <= $this->inactivityTimeoutSeconds) {
            return;
        }

        $this->tokenStorage->setToken(null);
        $session->invalidate();
        $event->setResponse($this->authenticationEntryPoint->start($request));
    }
}
