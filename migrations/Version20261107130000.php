<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Connexion Google / Facebook : identifiant du compte lié chez chaque fournisseur.
 */
final class Version20261107130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'User : ajout de google_id et facebook_id (connexion OAuth)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD google_id VARCHAR(255) DEFAULT NULL, ADD facebook_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64976F5C865 ON user (google_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6499BE8FD98 ON user (facebook_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_8D93D64976F5C865 ON user');
        $this->addSql('DROP INDEX UNIQ_8D93D6499BE8FD98 ON user');
        $this->addSql('ALTER TABLE user DROP google_id, DROP facebook_id');
    }
}
