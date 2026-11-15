<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Install the controlled Library category reference vocabulary.';
    }

    public function up(Schema $schema): void
    {
        $categories = [
            ['0199a001-0000-7000-8000-000000000001', 'Narrativa', 'narrativa'],
            ['0199a001-0000-7000-8000-000000000002', 'Fantascienza', 'fantascienza'],
            ['0199a001-0000-7000-8000-000000000003', 'Fantasy', 'fantasy'],
            ['0199a001-0000-7000-8000-000000000004', 'Classici', 'classici'],
            ['0199a001-0000-7000-8000-000000000005', 'Giallo e thriller', 'giallo-e-thriller'],
            ['0199a001-0000-7000-8000-000000000006', 'Horror', 'horror'],
            ['0199a001-0000-7000-8000-000000000007', 'Saggistica', 'saggistica'],
            ['0199a001-0000-7000-8000-000000000008', 'Informatica', 'informatica'],
        ];

        foreach ($categories as [$id, $name, $slug]) {
            $this->addSql(
                'INSERT INTO library_category (id, name, slug, active) VALUES (?, ?, ?, TRUE) ON CONFLICT (slug) DO NOTHING',
                [$id, $name, $slug],
            );
        }
    }

    public function down(Schema $schema): void
    {
    }
}
