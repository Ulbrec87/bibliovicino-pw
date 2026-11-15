<?php

declare(strict_types=1);

namespace App\Identity\Application\SetDiscoveryLocation;

use App\Identity\Application\DiscoveryLocation\DiscoveryLocationResult;
use App\Identity\Domain\User\UserRepository;
use LogicException;

final readonly class SetDiscoveryLocationHandler
{
    public function __construct(
        private UserRepository $users,
        private DiscoveryLocationApproximator $approximator,
    ) {
    }

    public function handle(SetDiscoveryLocationCommand $command): DiscoveryLocationResult
    {
        self::assertValidCoordinates($command->latitude, $command->longitude);

        $user = $this->users->findById($command->userId);

        if (null === $user) {
            throw new LogicException('The authenticated user could not be loaded.');
        }

        $location = $this->approximator->approximate($command->latitude, $command->longitude);
        $user->setDiscoveryLocation($location);
        $this->users->save($user);

        return DiscoveryLocationResult::fromLocation($location);
    }

    private static function assertValidCoordinates(float $latitude, float $longitude): void
    {
        if (
            !is_finite($latitude)
            || !is_finite($longitude)
            || $latitude < -90.0
            || $latitude > 90.0
            || $longitude < -180.0
            || $longitude > 180.0
        ) {
            throw new InvalidDiscoveryCoordinates('The submitted coordinates must be finite WGS84 values.');
        }
    }
}
