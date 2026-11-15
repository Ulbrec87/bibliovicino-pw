<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260822120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the Identity user table with UUID identifiers and case-insensitive email uniqueness.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE identity_user (id UUID NOT NULL, email VARCHAR(255) NOT NULL, normalized_email VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, display_name VARCHAR(255) NOT NULL, roles JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_39E1FCD3BCCCEC4 ON identity_user (normalized_email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE identity_user');
    }
}
