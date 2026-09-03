<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829215851 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE survey_choice (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, number_voter INT NOT NULL, survey_id INT DEFAULT NULL, INDEX IDX_E9A592FEB3FE509D (survey_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE survey_choice ADD CONSTRAINT FK_E9A592FEB3FE509D FOREIGN KEY (survey_id) REFERENCES survey (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE survey_choice DROP FOREIGN KEY FK_E9A592FEB3FE509D');
        $this->addSql('DROP TABLE survey_choice');
    }
}
