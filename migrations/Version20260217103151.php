<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217103151 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE flashcard_decks DROP FOREIGN KEY `FK_3DBF4D223EDC87`');
        $this->addSql('ALTER TABLE flashcard_decks DROP FOREIGN KEY `FK_3DBF4D27E3C61F9`');
        $this->addSql('DROP INDEX idx_3dbf4d223edc87 ON flashcard_decks');
        $this->addSql('CREATE INDEX idx_deck_subject ON flashcard_decks (subject_id)');
        $this->addSql('DROP INDEX idx_3dbf4d27e3c61f9 ON flashcard_decks');
        $this->addSql('CREATE INDEX idx_deck_owner ON flashcard_decks (owner_id)');
        $this->addSql('ALTER TABLE flashcard_decks ADD CONSTRAINT `FK_3DBF4D223EDC87` FOREIGN KEY (subject_id) REFERENCES subjects (id)');
        $this->addSql('ALTER TABLE flashcard_decks ADD CONSTRAINT `FK_3DBF4D27E3C61F9` FOREIGN KEY (owner_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE flashcard_review_states DROP FOREIGN KEY `FK_6D160B8FA76ED395`');
        $this->addSql('ALTER TABLE flashcard_review_states DROP FOREIGN KEY `FK_6D160B8FC5D16576`');
        $this->addSql('DROP INDEX idx_6d160b8fa76ed395 ON flashcard_review_states');
        $this->addSql('CREATE INDEX idx_frs_user ON flashcard_review_states (user_id)');
        $this->addSql('DROP INDEX idx_6d160b8fc5d16576 ON flashcard_review_states');
        $this->addSql('CREATE INDEX idx_frs_flashcard ON flashcard_review_states (flashcard_id)');
        $this->addSql('ALTER TABLE flashcard_review_states ADD CONSTRAINT `FK_6D160B8FA76ED395` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE flashcard_review_states ADD CONSTRAINT `FK_6D160B8FC5D16576` FOREIGN KEY (flashcard_id) REFERENCES flashcards (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_posts DROP FOREIGN KEY `FK_D871919DF675F31B`');
        $this->addSql('ALTER TABLE group_posts DROP FOREIGN KEY `FK_D871919DFE54D947`');
        $this->addSql('DROP INDEX idx_d871919dfe54d947 ON group_posts');
        $this->addSql('CREATE INDEX idx_gp_group ON group_posts (group_id)');
        $this->addSql('DROP INDEX idx_d871919df675f31b ON group_posts');
        $this->addSql('CREATE INDEX idx_gp_author ON group_posts (author_id)');
        $this->addSql('ALTER TABLE group_posts ADD CONSTRAINT `FK_D871919DF675F31B` FOREIGN KEY (author_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE group_posts ADD CONSTRAINT `FK_D871919DFE54D947` FOREIGN KEY (group_id) REFERENCES study_groups (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempts DROP FOREIGN KEY `FK_69031E21853CD175`');
        $this->addSql('ALTER TABLE quiz_attempts DROP FOREIGN KEY `FK_69031E21A76ED395`');
        $this->addSql('DROP INDEX idx_69031e21a76ed395 ON quiz_attempts');
        $this->addSql('CREATE INDEX idx_qa_user ON quiz_attempts (user_id)');
        $this->addSql('DROP INDEX idx_69031e21853cd175 ON quiz_attempts');
        $this->addSql('CREATE INDEX idx_qa_quiz ON quiz_attempts (quiz_id)');
        $this->addSql('ALTER TABLE quiz_attempts ADD CONSTRAINT `FK_69031E21853CD175` FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempts ADD CONSTRAINT `FK_69031E21A76ED395` FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE quizzes DROP FOREIGN KEY `FK_94DC9FB523EDC87`');
        $this->addSql('ALTER TABLE quizzes DROP FOREIGN KEY `FK_94DC9FB57E3C61F9`');
        $this->addSql('DROP INDEX idx_94dc9fb523edc87 ON quizzes');
        $this->addSql('CREATE INDEX idx_quiz_subject ON quizzes (subject_id)');
        $this->addSql('DROP INDEX idx_94dc9fb57e3c61f9 ON quizzes');
        $this->addSql('CREATE INDEX idx_quiz_owner ON quizzes (owner_id)');
        $this->addSql('ALTER TABLE quizzes ADD CONSTRAINT `FK_94DC9FB523EDC87` FOREIGN KEY (subject_id) REFERENCES subjects (id)');
        $this->addSql('ALTER TABLE quizzes ADD CONSTRAINT `FK_94DC9FB57E3C61F9` FOREIGN KEY (owner_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE flashcard_decks DROP FOREIGN KEY FK_3DBF4D27E3C61F9');
        $this->addSql('ALTER TABLE flashcard_decks DROP FOREIGN KEY FK_3DBF4D223EDC87');
        $this->addSql('DROP INDEX idx_deck_owner ON flashcard_decks');
        $this->addSql('CREATE INDEX IDX_3DBF4D27E3C61F9 ON flashcard_decks (owner_id)');
        $this->addSql('DROP INDEX idx_deck_subject ON flashcard_decks');
        $this->addSql('CREATE INDEX IDX_3DBF4D223EDC87 ON flashcard_decks (subject_id)');
        $this->addSql('ALTER TABLE flashcard_decks ADD CONSTRAINT FK_3DBF4D27E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE flashcard_decks ADD CONSTRAINT FK_3DBF4D223EDC87 FOREIGN KEY (subject_id) REFERENCES subjects (id)');
        $this->addSql('ALTER TABLE flashcard_review_states DROP FOREIGN KEY FK_6D160B8FA76ED395');
        $this->addSql('ALTER TABLE flashcard_review_states DROP FOREIGN KEY FK_6D160B8FC5D16576');
        $this->addSql('DROP INDEX idx_frs_flashcard ON flashcard_review_states');
        $this->addSql('CREATE INDEX IDX_6D160B8FC5D16576 ON flashcard_review_states (flashcard_id)');
        $this->addSql('DROP INDEX idx_frs_user ON flashcard_review_states');
        $this->addSql('CREATE INDEX IDX_6D160B8FA76ED395 ON flashcard_review_states (user_id)');
        $this->addSql('ALTER TABLE flashcard_review_states ADD CONSTRAINT FK_6D160B8FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE flashcard_review_states ADD CONSTRAINT FK_6D160B8FC5D16576 FOREIGN KEY (flashcard_id) REFERENCES flashcards (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_posts DROP FOREIGN KEY FK_D871919DFE54D947');
        $this->addSql('ALTER TABLE group_posts DROP FOREIGN KEY FK_D871919DF675F31B');
        $this->addSql('DROP INDEX idx_gp_group ON group_posts');
        $this->addSql('CREATE INDEX IDX_D871919DFE54D947 ON group_posts (group_id)');
        $this->addSql('DROP INDEX idx_gp_author ON group_posts');
        $this->addSql('CREATE INDEX IDX_D871919DF675F31B ON group_posts (author_id)');
        $this->addSql('ALTER TABLE group_posts ADD CONSTRAINT FK_D871919DFE54D947 FOREIGN KEY (group_id) REFERENCES study_groups (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_posts ADD CONSTRAINT FK_D871919DF675F31B FOREIGN KEY (author_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE quizzes DROP FOREIGN KEY FK_94DC9FB57E3C61F9');
        $this->addSql('ALTER TABLE quizzes DROP FOREIGN KEY FK_94DC9FB523EDC87');
        $this->addSql('DROP INDEX idx_quiz_owner ON quizzes');
        $this->addSql('CREATE INDEX IDX_94DC9FB57E3C61F9 ON quizzes (owner_id)');
        $this->addSql('DROP INDEX idx_quiz_subject ON quizzes');
        $this->addSql('CREATE INDEX IDX_94DC9FB523EDC87 ON quizzes (subject_id)');
        $this->addSql('ALTER TABLE quizzes ADD CONSTRAINT FK_94DC9FB57E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE quizzes ADD CONSTRAINT FK_94DC9FB523EDC87 FOREIGN KEY (subject_id) REFERENCES subjects (id)');
        $this->addSql('ALTER TABLE quiz_attempts DROP FOREIGN KEY FK_69031E21A76ED395');
        $this->addSql('ALTER TABLE quiz_attempts DROP FOREIGN KEY FK_69031E21853CD175');
        $this->addSql('DROP INDEX idx_qa_user ON quiz_attempts');
        $this->addSql('CREATE INDEX IDX_69031E21A76ED395 ON quiz_attempts (user_id)');
        $this->addSql('DROP INDEX idx_qa_quiz ON quiz_attempts');
        $this->addSql('CREATE INDEX IDX_69031E21853CD175 ON quiz_attempts (quiz_id)');
        $this->addSql('ALTER TABLE quiz_attempts ADD CONSTRAINT FK_69031E21A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE quiz_attempts ADD CONSTRAINT FK_69031E21853CD175 FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE');
    }
}
