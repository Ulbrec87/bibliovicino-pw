<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep loan offers only and preserve existing copy conditions in notes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE library_book_copy ALTER notes TYPE VARCHAR(1100)');
        $this->addSql(<<<'SQL'
            UPDATE library_book_copy
            SET notes = CASE WHEN notes IS NULL OR notes = '' THEN '' ELSE notes || E'\n' END
                || 'Condizione: ' || CASE condition
                    WHEN 'LIKE_NEW' THEN 'Come nuovo'
                    WHEN 'GOOD' THEN 'Buona'
                    WHEN 'FAIR' THEN 'Discreta'
                    WHEN 'WORN' THEN 'Usurata' END,
                updated_at = CURRENT_TIMESTAMP
            WHERE condition IS NOT NULL
            SQL);
        $this->addSql('UPDATE library_book_copy SET is_published = FALSE, updated_at = CURRENT_TIMESTAMP WHERE is_published = TRUE AND offered_for_loan = FALSE');
        $this->addSql('ALTER TABLE library_book_copy DROP CONSTRAINT chk_library_book_copy_publication_offer, DROP CONSTRAINT chk_library_book_copy_condition');
        $this->addSql('ALTER TABLE library_book_copy DROP COLUMN offered_for_exchange, DROP COLUMN condition');
        $this->addSql('ALTER TABLE library_book_copy ADD CONSTRAINT chk_library_book_copy_publication_offer CHECK (NOT is_published OR offered_for_loan)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Restore the pre-migration backup to recover structured conditions and exchange offers.');
    }
}
