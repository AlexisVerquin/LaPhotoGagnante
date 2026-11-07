<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Theme::$winner passe de OneToOne à ManyToOne : un membre peut gagner plusieurs concours.
 * On remplace l'index UNIQUE sur theme.winner_id par un index simple.
 * L'index simple est créé AVANT la suppression de l'unique, car MySQL refuse de supprimer
 * le seul index utilisé par une clé étrangère.
 */
final class Version20261107120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Theme.winner : OneToOne -> ManyToOne (suppression de l\'index unique sur winner_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_9775E7085DFCD4B8 ON theme (winner_id)');
        $this->addSql('DROP INDEX UNIQ_9775E7085DFCD4B8 ON theme');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9775E7085DFCD4B8 ON theme (winner_id)');
        $this->addSql('DROP INDEX IDX_9775E7085DFCD4B8 ON theme');
    }
}
