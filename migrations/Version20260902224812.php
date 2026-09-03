<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260902224812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE theme ADD theme_choice_id INT DEFAULT NULL, DROP name, DROP description');
        $this->addSql('ALTER TABLE theme ADD CONSTRAINT FK_9775E7085745BC5C FOREIGN KEY (theme_choice_id) REFERENCES survey_choice (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9775E7085745BC5C ON theme (theme_choice_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE theme DROP FOREIGN KEY FK_9775E7085745BC5C');
        $this->addSql('DROP INDEX UNIQ_9775E7085745BC5C ON theme');
        $this->addSql('ALTER TABLE theme ADD name VARCHAR(100) NOT NULL, ADD description LONGTEXT DEFAULT NULL, DROP theme_choice_id');
    }
}
