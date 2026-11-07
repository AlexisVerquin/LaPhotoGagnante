<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Abonnement aux emails de nouveau concours, statut « fermé » des messages de contact,
 * et un concours garde son historique si son gagnant supprime son compte.
 */
final class Version20261107140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Emails de nouveau concours, clôture des messages, gagnant mis à NULL à la suppression du compte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact ADD closed_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE theme DROP FOREIGN KEY `FK_9775E7085DFCD4B8`');
        $this->addSql('ALTER TABLE theme ADD notified_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE theme ADD CONSTRAINT FK_9775E7085DFCD4B8 FOREIGN KEY (winner_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user ADD notify_new_theme TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact DROP closed_at');
        $this->addSql('ALTER TABLE theme DROP FOREIGN KEY FK_9775E7085DFCD4B8');
        $this->addSql('ALTER TABLE theme DROP notified_at');
        $this->addSql('ALTER TABLE theme ADD CONSTRAINT `FK_9775E7085DFCD4B8` FOREIGN KEY (winner_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE user DROP notify_new_theme');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
