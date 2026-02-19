<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260203182544 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE flashcard_review_states (id INT AUTO_INCREMENT NOT NULL, repetitions INT NOT NULL, interval_days INT NOT NULL, ease_factor NUMERIC(4, 2) NOT NULL, due_at DATE NOT NULL, last_reviewed_at DATETIME DEFAULT NULL, user_id INT NOT NULL, flashcard_id INT NOT NULL, INDEX IDX_6D160B8FA76ED395 (user_id), INDEX IDX_6D160B8FC5D16576 (flashcard_id), UNIQUE INDEX unique_user_flashcard (user_id, flashcard_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE flashcards (id INT AUTO_INCREMENT NOT NULL, front LONGTEXT NOT NULL, back LONGTEXT NOT NULL, hint VARCHAR(500) DEFAULT NULL, position INT NOT NULL, created_at DATETIME NOT NULL, deck_id INT NOT NULL, INDEX IDX_62A226B5111948DC (deck_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE quiz_attempt_answers (id INT AUTO_INCREMENT NOT NULL, question_index INT NOT NULL, selected_choice_key VARCHAR(100) NOT NULL, is_correct TINYINT NOT NULL, attempt_id INT NOT NULL, INDEX IDX_AF912285B191BE6B (attempt_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE quiz_attempts (id INT AUTO_INCREMENT NOT NULL, started_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, score NUMERIC(5, 2) DEFAULT NULL, total_questions INT NOT NULL, correct_count INT NOT NULL, duration_seconds INT DEFAULT NULL, user_id INT NOT NULL, quiz_id INT NOT NULL, INDEX IDX_69031E21A76ED395 (user_id), INDEX IDX_69031E21853CD175 (quiz_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE flashcard_review_states ADD CONSTRAINT FK_6D160B8FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE flashcard_review_states ADD CONSTRAINT FK_6D160B8FC5D16576 FOREIGN KEY (flashcard_id) REFERENCES flashcards (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE flashcards ADD CONSTRAINT FK_62A226B5111948DC FOREIGN KEY (deck_id) REFERENCES flashcard_decks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempt_answers ADD CONSTRAINT FK_AF912285B191BE6B FOREIGN KEY (attempt_id) REFERENCES quiz_attempts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempts ADD CONSTRAINT FK_69031E21A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE quiz_attempts ADD CONSTRAINT FK_69031E21853CD175 FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX unique_subject_order ON chapters (subject_id, order_no)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE flashcard_review_states DROP FOREIGN KEY FK_6D160B8FA76ED395');
        $this->addSql('ALTER TABLE flashcard_review_states DROP FOREIGN KEY FK_6D160B8FC5D16576');
        $this->addSql('ALTER TABLE flashcards DROP FOREIGN KEY FK_62A226B5111948DC');
        $this->addSql('ALTER TABLE quiz_attempt_answers DROP FOREIGN KEY FK_AF912285B191BE6B');
        $this->addSql('ALTER TABLE quiz_attempts DROP FOREIGN KEY FK_69031E21A76ED395');
        $this->addSql('ALTER TABLE quiz_attempts DROP FOREIGN KEY FK_69031E21853CD175');
        $this->addSql('DROP TABLE flashcard_review_states');
        $this->addSql('DROP TABLE flashcards');
        $this->addSql('DROP TABLE quiz_attempt_answers');
        $this->addSql('DROP TABLE quiz_attempts');
        $this->addSql('DROP INDEX unique_subject_order ON chapters');
    }
}
