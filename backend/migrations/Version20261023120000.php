<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store loan requests and enforce one active request per physical copy.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE loan_request (id UUID NOT NULL, book_copy_id UUID NOT NULL, requester_id UUID NOT NULL, status VARCHAR(9) NOT NULL, requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id), CONSTRAINT chk_loan_request_status CHECK (status IN ('PENDING', 'ACCEPTED', 'LENT', 'RETURNED', 'REJECTED', 'CANCELLED')), CONSTRAINT fk_loan_request_copy FOREIGN KEY (book_copy_id) REFERENCES library_book_copy (id) ON DELETE RESTRICT)");
        $this->addSql('CREATE INDEX idx_loan_request_copy ON loan_request (book_copy_id)');
        $this->addSql('CREATE INDEX idx_loan_request_requester ON loan_request (requester_id)');
        $this->addSql("CREATE UNIQUE INDEX uniq_loan_request_active_copy ON loan_request (book_copy_id) WHERE status IN ('PENDING', 'ACCEPTED', 'LENT')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE loan_request');
    }
}
