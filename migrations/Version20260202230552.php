<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260202230552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ai_generation_logs (id INT AUTO_INCREMENT NOT NULL, feature VARCHAR(100) NOT NULL, input_json JSON NOT NULL, prompt LONGTEXT NOT NULL, output_json JSON DEFAULT NULL, status VARCHAR(50) NOT NULL, error_message LONGTEXT DEFAULT NULL, latency_ms INT DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT DEFAULT NULL, model_id INT DEFAULT NULL, INDEX IDX_70CF1573A76ED395 (user_id), INDEX IDX_70CF15737975B7E7 (model_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ai_models (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, provider VARCHAR(100) NOT NULL, base_url VARCHAR(500) NOT NULL, is_default TINYINT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX unique_model_url (name, base_url), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE chapters (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, order_no INT NOT NULL, summary LONGTEXT DEFAULT NULL, content LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, subject_id INT NOT NULL, created_by_id INT NOT NULL, INDEX IDX_C721437123EDC87 (subject_id), INDEX IDX_C7214371B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE flashcard_decks (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, template_key VARCHAR(100) DEFAULT NULL, cards JSON NOT NULL, is_published TINYINT NOT NULL, generated_by_ai TINYINT NOT NULL, ai_meta JSON DEFAULT NULL, created_at DATETIME NOT NULL, owner_id INT NOT NULL, subject_id INT NOT NULL, chapter_id INT DEFAULT NULL, INDEX IDX_3DBF4D27E3C61F9 (owner_id), INDEX IDX_3DBF4D223EDC87 (subject_id), INDEX IDX_3DBF4D2579F4768 (chapter_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE group_members (id INT AUTO_INCREMENT NOT NULL, member_role VARCHAR(50) NOT NULL, joined_at DATETIME NOT NULL, group_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_C3A086F3FE54D947 (group_id), INDEX IDX_C3A086F3A76ED395 (user_id), UNIQUE INDEX unique_group_user (group_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE group_posts (id INT AUTO_INCREMENT NOT NULL, post_type VARCHAR(50) NOT NULL, title VARCHAR(255) DEFAULT NULL, body LONGTEXT NOT NULL, attachment_url VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, group_id INT NOT NULL, author_id INT NOT NULL, parent_post_id INT DEFAULT NULL, INDEX IDX_D871919DFE54D947 (group_id), INDEX IDX_D871919DF675F31B (author_id), INDEX IDX_D871919D39C1776A (parent_post_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE plan_tasks (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, task_type VARCHAR(50) NOT NULL, start_at DATETIME NOT NULL, end_at DATETIME NOT NULL, status VARCHAR(50) NOT NULL, priority SMALLINT NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, plan_id INT NOT NULL, INDEX IDX_3FE206BE899029B (plan_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE quizzes (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, difficulty VARCHAR(50) NOT NULL, template_key VARCHAR(100) DEFAULT NULL, questions JSON NOT NULL, is_published TINYINT NOT NULL, generated_by_ai TINYINT NOT NULL, ai_meta JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, owner_id INT NOT NULL, subject_id INT NOT NULL, chapter_id INT DEFAULT NULL, INDEX IDX_94DC9FB57E3C61F9 (owner_id), INDEX IDX_94DC9FB523EDC87 (subject_id), INDEX IDX_94DC9FB5579F4768 (chapter_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE revision_plans (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, status VARCHAR(50) NOT NULL, generated_by_ai TINYINT NOT NULL, ai_meta JSON DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, subject_id INT NOT NULL, chapter_id INT DEFAULT NULL, INDEX IDX_18E9B2EEA76ED395 (user_id), INDEX IDX_18E9B2EE23EDC87 (subject_id), INDEX IDX_18E9B2EE579F4768 (chapter_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE study_groups (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, privacy VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, created_by_id INT NOT NULL, INDEX IDX_5038A158B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE subjects (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, code VARCHAR(50) DEFAULT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, created_by_id INT NOT NULL, UNIQUE INDEX UNIQ_AB25991777153098 (code), INDEX IDX_AB259917B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_profiles (id INT AUTO_INCREMENT NOT NULL, level VARCHAR(100) DEFAULT NULL, specialty VARCHAR(255) DEFAULT NULL, bio LONGTEXT DEFAULT NULL, avatar_url VARCHAR(500) DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_6BBD6130A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, full_name VARCHAR(255) NOT NULL, user_type VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ai_generation_logs ADD CONSTRAINT FK_70CF1573A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ai_generation_logs ADD CONSTRAINT FK_70CF15737975B7E7 FOREIGN KEY (model_id) REFERENCES ai_models (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE chapters ADD CONSTRAINT FK_C721437123EDC87 FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chapters ADD CONSTRAINT FK_C7214371B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE flashcard_decks ADD CONSTRAINT FK_3DBF4D27E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE flashcard_decks ADD CONSTRAINT FK_3DBF4D223EDC87 FOREIGN KEY (subject_id) REFERENCES subjects (id)');
        $this->addSql('ALTER TABLE flashcard_decks ADD CONSTRAINT FK_3DBF4D2579F4768 FOREIGN KEY (chapter_id) REFERENCES chapters (id)');
        $this->addSql('ALTER TABLE group_members ADD CONSTRAINT FK_C3A086F3FE54D947 FOREIGN KEY (group_id) REFERENCES study_groups (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_members ADD CONSTRAINT FK_C3A086F3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_posts ADD CONSTRAINT FK_D871919DFE54D947 FOREIGN KEY (group_id) REFERENCES study_groups (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_posts ADD CONSTRAINT FK_D871919DF675F31B FOREIGN KEY (author_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE group_posts ADD CONSTRAINT FK_D871919D39C1776A FOREIGN KEY (parent_post_id) REFERENCES group_posts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plan_tasks ADD CONSTRAINT FK_3FE206BE899029B FOREIGN KEY (plan_id) REFERENCES revision_plans (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quizzes ADD CONSTRAINT FK_94DC9FB57E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE quizzes ADD CONSTRAINT FK_94DC9FB523EDC87 FOREIGN KEY (subject_id) REFERENCES subjects (id)');
        $this->addSql('ALTER TABLE quizzes ADD CONSTRAINT FK_94DC9FB5579F4768 FOREIGN KEY (chapter_id) REFERENCES chapters (id)');
        $this->addSql('ALTER TABLE revision_plans ADD CONSTRAINT FK_18E9B2EEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE revision_plans ADD CONSTRAINT FK_18E9B2EE23EDC87 FOREIGN KEY (subject_id) REFERENCES subjects (id)');
        $this->addSql('ALTER TABLE revision_plans ADD CONSTRAINT FK_18E9B2EE579F4768 FOREIGN KEY (chapter_id) REFERENCES chapters (id)');
        $this->addSql('ALTER TABLE study_groups ADD CONSTRAINT FK_5038A158B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE subjects ADD CONSTRAINT FK_AB259917B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE user_profiles ADD CONSTRAINT FK_6BBD6130A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ai_generation_logs DROP FOREIGN KEY FK_70CF1573A76ED395');
        $this->addSql('ALTER TABLE ai_generation_logs DROP FOREIGN KEY FK_70CF15737975B7E7');
        $this->addSql('ALTER TABLE chapters DROP FOREIGN KEY FK_C721437123EDC87');
        $this->addSql('ALTER TABLE chapters DROP FOREIGN KEY FK_C7214371B03A8386');
        $this->addSql('ALTER TABLE flashcard_decks DROP FOREIGN KEY FK_3DBF4D27E3C61F9');
        $this->addSql('ALTER TABLE flashcard_decks DROP FOREIGN KEY FK_3DBF4D223EDC87');
        $this->addSql('ALTER TABLE flashcard_decks DROP FOREIGN KEY FK_3DBF4D2579F4768');
        $this->addSql('ALTER TABLE group_members DROP FOREIGN KEY FK_C3A086F3FE54D947');
        $this->addSql('ALTER TABLE group_members DROP FOREIGN KEY FK_C3A086F3A76ED395');
        $this->addSql('ALTER TABLE group_posts DROP FOREIGN KEY FK_D871919DFE54D947');
        $this->addSql('ALTER TABLE group_posts DROP FOREIGN KEY FK_D871919DF675F31B');
        $this->addSql('ALTER TABLE group_posts DROP FOREIGN KEY FK_D871919D39C1776A');
        $this->addSql('ALTER TABLE plan_tasks DROP FOREIGN KEY FK_3FE206BE899029B');
        $this->addSql('ALTER TABLE quizzes DROP FOREIGN KEY FK_94DC9FB57E3C61F9');
        $this->addSql('ALTER TABLE quizzes DROP FOREIGN KEY FK_94DC9FB523EDC87');
        $this->addSql('ALTER TABLE quizzes DROP FOREIGN KEY FK_94DC9FB5579F4768');
        $this->addSql('ALTER TABLE revision_plans DROP FOREIGN KEY FK_18E9B2EEA76ED395');
        $this->addSql('ALTER TABLE revision_plans DROP FOREIGN KEY FK_18E9B2EE23EDC87');
        $this->addSql('ALTER TABLE revision_plans DROP FOREIGN KEY FK_18E9B2EE579F4768');
        $this->addSql('ALTER TABLE study_groups DROP FOREIGN KEY FK_5038A158B03A8386');
        $this->addSql('ALTER TABLE subjects DROP FOREIGN KEY FK_AB259917B03A8386');
        $this->addSql('ALTER TABLE user_profiles DROP FOREIGN KEY FK_6BBD6130A76ED395');
        $this->addSql('DROP TABLE ai_generation_logs');
        $this->addSql('DROP TABLE ai_models');
        $this->addSql('DROP TABLE chapters');
        $this->addSql('DROP TABLE flashcard_decks');
        $this->addSql('DROP TABLE group_members');
        $this->addSql('DROP TABLE group_posts');
        $this->addSql('DROP TABLE plan_tasks');
        $this->addSql('DROP TABLE quizzes');
        $this->addSql('DROP TABLE revision_plans');
        $this->addSql('DROP TABLE study_groups');
        $this->addSql('DROP TABLE subjects');
        $this->addSql('DROP TABLE user_profiles');
        $this->addSql('DROP TABLE users');
    }
}
