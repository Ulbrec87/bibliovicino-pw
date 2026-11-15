<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http\DiscoveryLocation;

use App\Identity\Application\DisableDiscoveryLocation\DisableDiscoveryLocationCommand;
use App\Identity\Application\DisableDiscoveryLocation\DisableDiscoveryLocationHandler;
use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class DisableDiscoveryLocationController
{
    public function __construct(
        private DisableDiscoveryLocationHandler $disableDiscoveryLocation,
        private Security $security,
    ) {
    }

    #[Route('/api/profile/discovery-location', name: 'api_profile_discovery_location_disable', methods: ['DELETE'])]
    public function __invoke(): Response
    {
        $securityUser = $this->security->getUser();

        assert($securityUser instanceof SecurityUser);

        $this->disableDiscoveryLocation->handle(new DisableDiscoveryLocationCommand($securityUser->id()));

        $response = new Response(status: Response::HTTP_NO_CONTENT);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
