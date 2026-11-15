<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the Library BookCopy aggregate persistence model.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE library_book_copy (id UUID NOT NULL, book_id UUID NOT NULL, owner_id UUID NOT NULL, availability_status VARCHAR(11) NOT NULL, offered_for_loan BOOLEAN NOT NULL, offered_for_exchange BOOLEAN NOT NULL, is_published BOOLEAN NOT NULL, condition VARCHAR(8) DEFAULT NULL, notes VARCHAR(1000) DEFAULT NULL, archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id), CONSTRAINT fk_library_book_copy_book FOREIGN KEY (book_id) REFERENCES library_book (id) ON DELETE RESTRICT, CONSTRAINT chk_library_book_copy_publication_offer CHECK (NOT is_published OR offered_for_loan OR offered_for_exchange), CONSTRAINT chk_library_book_copy_archived_unpublished CHECK (archived_at IS NULL OR NOT is_published), CONSTRAINT chk_library_book_copy_availability CHECK (availability_status IN ('AVAILABLE', 'UNAVAILABLE')), CONSTRAINT chk_library_book_copy_condition CHECK (condition IS NULL OR condition IN ('LIKE_NEW', 'GOOD', 'FAIR', 'WORN')))");
        $this->addSql('CREATE INDEX idx_library_book_copy_owner ON library_book_copy (owner_id)');
        $this->addSql('CREATE INDEX idx_library_book_copy_book ON library_book_copy (book_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE library_book_copy');
    }
}
