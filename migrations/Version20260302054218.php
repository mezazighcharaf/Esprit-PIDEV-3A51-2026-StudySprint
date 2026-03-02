<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260302054218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE study_groups DROP FOREIGN KEY `FK_5038A158B03A8386`');
        $this->addSql('ALTER TABLE study_groups CHANGE created_by_id created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE study_groups ADD CONSTRAINT FK_5038A158B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE study_groups DROP FOREIGN KEY FK_5038A158B03A8386');
        $this->addSql('ALTER TABLE study_groups CHANGE created_by_id created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE study_groups ADD CONSTRAINT `FK_5038A158B03A8386` FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE CASCADE');
    }
}
