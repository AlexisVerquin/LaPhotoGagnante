<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260829205529 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add date Trait';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE user ALTER created_at DROP DEFAULT, ALTER updated_at DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP created_at, DROP updated_at');
    }
}
