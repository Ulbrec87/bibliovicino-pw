<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add copy-owned cover references without changing existing copies.'; }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE library_book_copy ADD cover_source VARCHAR(8) DEFAULT 'none' NOT NULL, ADD cover_reference VARCHAR(100) DEFAULT NULL");
        $this->addSql(<<<'SQL'
            ALTER TABLE library_book_copy ADD CONSTRAINT chk_library_book_copy_cover CHECK (
                (cover_source = 'none' AND cover_reference IS NULL)
                OR (cover_source = 'external' AND cover_reference IS NOT NULL AND cover_reference ~ '^open-library:[1-9][0-9]{0,18}$')
                OR (cover_source = 'uploaded' AND cover_reference IS NOT NULL AND cover_reference ~ '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$')
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE library_book_copy DROP CONSTRAINT chk_library_book_copy_cover, DROP cover_reference, DROP cover_source');
    }
}
