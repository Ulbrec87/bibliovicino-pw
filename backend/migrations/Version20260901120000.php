<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the partial GiST index used by approximate DiscoveryLocation radius searches.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_identity_user_discovery_location_gist ON identity_user USING GIST (discovery_location) WHERE discovery_location IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_identity_user_discovery_location_gist');
    }
}
