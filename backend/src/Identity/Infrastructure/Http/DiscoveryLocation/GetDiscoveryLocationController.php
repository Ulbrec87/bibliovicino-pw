<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http\DiscoveryLocation;

use App\Identity\Application\GetDiscoveryLocation\GetDiscoveryLocationHandler;
use App\Identity\Application\GetDiscoveryLocation\GetDiscoveryLocationCommand;
use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetDiscoveryLocationController
{
    public function __construct(
        private GetDiscoveryLocationHandler $getDiscoveryLocation,
        private Security $security,
    ) {
    }

    #[Route('/api/profile/discovery-location', name: 'api_profile_discovery_location_get', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $securityUser = $this->security->getUser();

        assert($securityUser instanceof SecurityUser);

        $result = $this->getDiscoveryLocation->handle(new GetDiscoveryLocationCommand($securityUser->id()));
        $response = new JsonResponse(DiscoveryLocationResponse::fromResult($result));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
