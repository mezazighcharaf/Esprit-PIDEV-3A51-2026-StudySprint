<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217105746 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE badges (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT NOT NULL, icon VARCHAR(10) NOT NULL, color VARCHAR(20) NOT NULL, UNIQUE INDEX UNIQ_78F6539A77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_badges (id INT AUTO_INCREMENT NOT NULL, earned_at DATETIME NOT NULL, user_id INT NOT NULL, badge_id INT NOT NULL, INDEX IDX_1DA448A7F7A2C2FC (badge_id), INDEX idx_ub_user (user_id), UNIQUE INDEX unique_user_badge (user_id, badge_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_badges ADD CONSTRAINT FK_1DA448A7A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_badges ADD CONSTRAINT FK_1DA448A7F7A2C2FC FOREIGN KEY (badge_id) REFERENCES badges (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_badges DROP FOREIGN KEY FK_1DA448A7A76ED395');
        $this->addSql('ALTER TABLE user_badges DROP FOREIGN KEY FK_1DA448A7F7A2C2FC');
        $this->addSql('DROP TABLE badges');
        $this->addSql('DROP TABLE user_badges');
    }
}
