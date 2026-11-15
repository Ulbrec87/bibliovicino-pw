<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http\DiscoveryLocation;

use App\Identity\Application\DiscoveryLocation\DiscoveryLocationResult;

final class DiscoveryLocationResponse
{
    /** @return array{discoveryLocation: null|array{representativePoint: array{latitude: float, longitude: float}}} */
    public static function fromResult(DiscoveryLocationResult $result): array
    {
        if (!$result->isConfigured()) {
            return ['discoveryLocation' => null];
        }

        return [
            'discoveryLocation' => [
                'representativePoint' => [
                    'latitude' => $result->latitude,
                    'longitude' => $result->longitude,
                ],
            ],
        ];
    }

    private function __construct()
    {
    }
}
