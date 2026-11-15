<?php

declare(strict_types=1);

namespace App\ReadModel\AdminStatistics\Infrastructure\Http;

use App\ReadModel\AdminStatistics\Application\GetAdminStatistics\GetAdminStatisticsHandler;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetAdminStatisticsController
{
    public function __construct(private GetAdminStatisticsHandler $handler)
    {
    }
    #[Route('/api/admin/statistics', name: 'api_admin_statistics', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(['statistics' => $this->handler->handle()], headers: ['Cache-Control' => 'private, no-store']);
    }
}
