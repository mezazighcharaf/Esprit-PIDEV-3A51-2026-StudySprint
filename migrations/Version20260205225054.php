<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260205225054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE teacher_certification_requests (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, motivation LONGTEXT DEFAULT NULL, reason LONGTEXT DEFAULT NULL, requested_at DATETIME NOT NULL, reviewed_at DATETIME DEFAULT NULL, user_id INT NOT NULL, reviewed_by_id INT DEFAULT NULL, INDEX IDX_405347E3A76ED395 (user_id), INDEX IDX_405347E3FC6B21F1 (reviewed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE teacher_certification_requests ADD CONSTRAINT FK_405347E3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE teacher_certification_requests ADD CONSTRAINT FK_405347E3FC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE teacher_certification_requests DROP FOREIGN KEY FK_405347E3A76ED395');
        $this->addSql('ALTER TABLE teacher_certification_requests DROP FOREIGN KEY FK_405347E3FC6B21F1');
        $this->addSql('DROP TABLE teacher_certification_requests');
    }
}
