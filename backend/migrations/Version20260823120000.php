<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the Library bibliographic core: books, authors, authorships and categories.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE library_author (id UUID NOT NULL, name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_library_author_name_ci ON library_author (LOWER(name))');

        $this->addSql('CREATE TABLE library_category (id UUID NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(100) NOT NULL, active BOOLEAN DEFAULT TRUE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_library_category_slug ON library_category (slug)');

        $this->addSql('CREATE TABLE library_book (id UUID NOT NULL, title VARCHAR(255) NOT NULL, publication_year SMALLINT DEFAULT NULL, isbn VARCHAR(13) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id), CONSTRAINT chk_library_book_publication_year CHECK (publication_year IS NULL OR publication_year >= 1000))');
        $this->addSql('CREATE UNIQUE INDEX uniq_library_book_isbn ON library_book (isbn)');

        $this->addSql('CREATE TABLE library_book_author (book_id UUID NOT NULL, author_id UUID NOT NULL, position SMALLINT NOT NULL, PRIMARY KEY(book_id, author_id), CONSTRAINT fk_library_book_author_book FOREIGN KEY (book_id) REFERENCES library_book (id) ON DELETE CASCADE, CONSTRAINT fk_library_book_author_author FOREIGN KEY (author_id) REFERENCES library_author (id) ON DELETE RESTRICT, CONSTRAINT chk_library_book_author_position CHECK (position >= 0 AND position <= 9))');
        $this->addSql('CREATE UNIQUE INDEX uniq_library_book_author_position ON library_book_author (book_id, position)');
        $this->addSql('CREATE INDEX idx_library_book_author_author_book ON library_book_author (author_id, book_id)');

        $this->addSql('CREATE TABLE library_book_category (book_id UUID NOT NULL, category_id UUID NOT NULL, PRIMARY KEY(book_id, category_id), CONSTRAINT fk_library_book_category_book FOREIGN KEY (book_id) REFERENCES library_book (id) ON DELETE CASCADE, CONSTRAINT fk_library_book_category_category FOREIGN KEY (category_id) REFERENCES library_category (id) ON DELETE RESTRICT)');
        $this->addSql('CREATE INDEX idx_library_book_category_category_book ON library_book_category (category_id, book_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE library_book_category');
        $this->addSql('DROP TABLE library_book_author');
        $this->addSql('DROP TABLE library_book');
        $this->addSql('DROP TABLE library_category');
        $this->addSql('DROP TABLE library_author');
    }
}
