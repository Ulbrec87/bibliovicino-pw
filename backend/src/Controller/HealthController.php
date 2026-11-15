<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class HealthController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(Connection $connection): JsonResponse
    {
        try {
            $postgisVersion = $connection->fetchOne('SELECT PostGIS_Version()');

            return new JsonResponse([
                'status' => 'ok',
                'database' => 'ok',
                'postgis' => [
                    'status' => 'ok',
                    'version' => $postgisVersion,
                ],
            ]);
        } catch (Throwable) {
            return new JsonResponse([
                'status' => 'error',
                'database' => 'unavailable',
                'postgis' => null,
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
