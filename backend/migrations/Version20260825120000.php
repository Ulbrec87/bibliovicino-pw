<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the optional approximate PostGIS DiscoveryLocation to Identity users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_user ADD discovery_location GEOGRAPHY(POINT, 4326) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_user DROP discovery_location');
    }
}
