<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217102814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE quiz_ratings (id INT AUTO_INCREMENT NOT NULL, score SMALLINT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, quiz_id INT NOT NULL, INDEX IDX_DCECAA69A76ED395 (user_id), INDEX IDX_DCECAA69853CD175 (quiz_id), UNIQUE INDEX unique_user_quiz_rating (user_id, quiz_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE quiz_ratings ADD CONSTRAINT FK_DCECAA69A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE quiz_ratings ADD CONSTRAINT FK_DCECAA69853CD175 FOREIGN KEY (quiz_id) REFERENCES quizzes (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE quiz_ratings DROP FOREIGN KEY FK_DCECAA69A76ED395');
        $this->addSql('ALTER TABLE quiz_ratings DROP FOREIGN KEY FK_DCECAA69853CD175');
        $this->addSql('DROP TABLE quiz_ratings');
    }
}
