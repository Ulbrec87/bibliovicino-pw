<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260822180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store one canonical user email and record account creation time.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE identity_user ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT (CURRENT_TIMESTAMP AT TIME ZONE 'UTC') NOT NULL");
        $this->addSql('ALTER TABLE identity_user ALTER COLUMN created_at DROP DEFAULT');
        $this->addSql('UPDATE identity_user SET email = LOWER(BTRIM(email))');
        $this->addSql('DROP INDEX UNIQ_39E1FCD3BCCCEC4');
        $this->addSql('ALTER TABLE identity_user DROP normalized_email');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_39E1FCDE7927C74 ON identity_user (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_user ADD normalized_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE identity_user SET normalized_email = email');
        $this->addSql('ALTER TABLE identity_user ALTER COLUMN normalized_email SET NOT NULL');
        $this->addSql('DROP INDEX UNIQ_39E1FCDE7927C74');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_39E1FCD3BCCCEC4 ON identity_user (normalized_email)');
        $this->addSql('ALTER TABLE identity_user DROP created_at');
    }
}
