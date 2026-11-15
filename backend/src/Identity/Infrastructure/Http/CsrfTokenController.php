<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final readonly class CsrfTokenController
{
    public function __construct(private CsrfTokenManagerInterface $csrfTokens)
    {
    }

    #[Route('/api/csrf-token', name: 'api_csrf_token', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $response = new JsonResponse([
            'token' => $this->csrfTokens->getToken('api')->getValue(),
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
