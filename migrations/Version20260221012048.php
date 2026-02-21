<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221012048 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE group_invitation ADD token VARCHAR(64) DEFAULT NULL, ADD message LONGTEXT DEFAULT NULL, ADD expires_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_26D000105F37A13B ON group_invitation (token)');
        $this->addSql('CREATE INDEX idx_invitation_token ON group_invitation (token)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_26D000105F37A13B ON group_invitation');
        $this->addSql('DROP INDEX idx_invitation_token ON group_invitation');
        $this->addSql('ALTER TABLE group_invitation DROP token, DROP message, DROP expires_at');
    }
}
