<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222051903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bot_interaction (id INT AUTO_INCREMENT NOT NULL, question LONGTEXT NOT NULL, response LONGTEXT NOT NULL, tokens_used INT DEFAULT 0 NOT NULL, response_time_ms INT DEFAULT NULL, feedback VARCHAR(15) DEFAULT NULL, created_at DATETIME NOT NULL, group_id INT NOT NULL, post_id INT NOT NULL, comment_id INT DEFAULT NULL, triggered_by_id INT NOT NULL, INDEX IDX_8F8A536EFE54D947 (group_id), INDEX IDX_8F8A536EF8697D13 (comment_id), INDEX idx_bot_group_created (group_id, created_at), INDEX idx_bot_triggered_by (triggered_by_id), INDEX idx_bot_post (post_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE chatbot_config (id INT AUTO_INCREMENT NOT NULL, is_enabled TINYINT DEFAULT 1 NOT NULL, bot_name VARCHAR(50) DEFAULT \'StudyBot\' NOT NULL, personality VARCHAR(20) DEFAULT \'tutor\' NOT NULL, subject_context VARCHAR(200) DEFAULT NULL, trigger_mode VARCHAR(20) DEFAULT \'mention\' NOT NULL, trigger_keywords JSON NOT NULL, max_response_length INT DEFAULT 500 NOT NULL, language VARCHAR(5) DEFAULT \'fr\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, group_id INT NOT NULL, UNIQUE INDEX uniq_chatbot_group (group_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE group_invitation (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, invited_at DATETIME NOT NULL, code VARCHAR(32) NOT NULL, status VARCHAR(10) NOT NULL, role VARCHAR(20) NOT NULL, responded_at DATETIME DEFAULT NULL, token VARCHAR(64) DEFAULT NULL, message LONGTEXT DEFAULT NULL, expires_at DATETIME DEFAULT NULL, group_id INT NOT NULL, invited_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_26D0001077153098 (code), UNIQUE INDEX UNIQ_26D000105F37A13B (token), INDEX IDX_26D00010FE54D947 (group_id), INDEX IDX_26D00010A7B4A7E3 (invited_by_id), INDEX idx_invitation_email (email), INDEX idx_invitation_status (status), INDEX idx_invitation_code (code), INDEX idx_invitation_token (token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post_comment (id INT AUTO_INCREMENT NOT NULL, depth SMALLINT DEFAULT 0 NOT NULL, body LONGTEXT NOT NULL, is_bot TINYINT DEFAULT 0 NOT NULL, bot_name VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, post_id INT NOT NULL, author_id INT NOT NULL, parent_comment_id INT DEFAULT NULL, INDEX idx_comment_post (post_id), INDEX idx_comment_author (author_id), INDEX idx_comment_parent (parent_comment_id), INDEX idx_comment_created (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post_like (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, post_id INT NOT NULL, user_id INT NOT NULL, INDEX idx_like_post (post_id), INDEX idx_like_user (user_id), UNIQUE INDEX uniq_post_user_like (post_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post_rating (id INT AUTO_INCREMENT NOT NULL, rating SMALLINT NOT NULL, created_at DATETIME NOT NULL, post_id INT NOT NULL, user_id INT NOT NULL, INDEX idx_rating_post (post_id), INDEX idx_rating_user (user_id), UNIQUE INDEX uniq_post_user_rating (post_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reactivation_request (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, status VARCHAR(50) NOT NULL, comment LONGTEXT DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_1B8F23C8A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE bot_interaction ADD CONSTRAINT FK_8F8A536EFE54D947 FOREIGN KEY (group_id) REFERENCES study_groups (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bot_interaction ADD CONSTRAINT FK_8F8A536E4B89032C FOREIGN KEY (post_id) REFERENCES group_posts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bot_interaction ADD CONSTRAINT FK_8F8A536EF8697D13 FOREIGN KEY (comment_id) REFERENCES post_comment (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE bot_interaction ADD CONSTRAINT FK_8F8A536E63C5923F FOREIGN KEY (triggered_by_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chatbot_config ADD CONSTRAINT FK_46FCC9C3FE54D947 FOREIGN KEY (group_id) REFERENCES study_groups (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_invitation ADD CONSTRAINT FK_26D00010FE54D947 FOREIGN KEY (group_id) REFERENCES study_groups (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_invitation ADD CONSTRAINT FK_26D00010A7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_comment ADD CONSTRAINT FK_A99CE55F4B89032C FOREIGN KEY (post_id) REFERENCES group_posts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_comment ADD CONSTRAINT FK_A99CE55FF675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_comment ADD CONSTRAINT FK_A99CE55FBF2AF943 FOREIGN KEY (parent_comment_id) REFERENCES post_comment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_like ADD CONSTRAINT FK_653627B84B89032C FOREIGN KEY (post_id) REFERENCES group_posts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_like ADD CONSTRAINT FK_653627B8A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_rating ADD CONSTRAINT FK_E8ABC2474B89032C FOREIGN KEY (post_id) REFERENCES group_posts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_rating ADD CONSTRAINT FK_E8ABC247A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reactivation_request ADD CONSTRAINT FK_1B8F23C8A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE study_groups DROP FOREIGN KEY `FK_5038A158B03A8386`');
        $this->addSql('ALTER TABLE study_groups ADD subject VARCHAR(100) DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD last_activity DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE study_groups ADD CONSTRAINT FK_5038A158B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_group_privacy ON study_groups (privacy)');
        $this->addSql('CREATE INDEX idx_group_created ON study_groups (created_at)');
        $this->addSql('CREATE INDEX idx_group_activity ON study_groups (last_activity)');
        $this->addSql('ALTER TABLE users ADD nom VARCHAR(255) NOT NULL, ADD prenom VARCHAR(255) NOT NULL, ADD mot_de_passe VARCHAR(255) NOT NULL, ADD role VARCHAR(255) DEFAULT NULL, ADD reset_token VARCHAR(255) DEFAULT NULL, ADD reset_token_expires_at DATETIME DEFAULT NULL, ADD last_activity_at DATETIME DEFAULT NULL, ADD pays VARCHAR(2) DEFAULT NULL, ADD telephone VARCHAR(20) DEFAULT NULL, ADD annees_experience INT DEFAULT NULL, ADD face_descriptor JSON DEFAULT NULL, ADD discr VARCHAR(255) NOT NULL, ADD age INT DEFAULT NULL, ADD sexe VARCHAR(10) DEFAULT NULL, ADD etablissement VARCHAR(255) DEFAULT NULL, ADD niveau VARCHAR(50) DEFAULT NULL, ADD specialite VARCHAR(255) DEFAULT NULL, ADD niveau_enseignement VARCHAR(255) DEFAULT NULL, DROP password, DROP roles, DROP full_name, CHANGE user_type statut VARCHAR(50) NOT NULL, CHANGE created_at date_inscription DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bot_interaction DROP FOREIGN KEY FK_8F8A536EFE54D947');
        $this->addSql('ALTER TABLE bot_interaction DROP FOREIGN KEY FK_8F8A536E4B89032C');
        $this->addSql('ALTER TABLE bot_interaction DROP FOREIGN KEY FK_8F8A536EF8697D13');
        $this->addSql('ALTER TABLE bot_interaction DROP FOREIGN KEY FK_8F8A536E63C5923F');
        $this->addSql('ALTER TABLE chatbot_config DROP FOREIGN KEY FK_46FCC9C3FE54D947');
        $this->addSql('ALTER TABLE group_invitation DROP FOREIGN KEY FK_26D00010FE54D947');
        $this->addSql('ALTER TABLE group_invitation DROP FOREIGN KEY FK_26D00010A7B4A7E3');
        $this->addSql('ALTER TABLE post_comment DROP FOREIGN KEY FK_A99CE55F4B89032C');
        $this->addSql('ALTER TABLE post_comment DROP FOREIGN KEY FK_A99CE55FF675F31B');
        $this->addSql('ALTER TABLE post_comment DROP FOREIGN KEY FK_A99CE55FBF2AF943');
        $this->addSql('ALTER TABLE post_like DROP FOREIGN KEY FK_653627B84B89032C');
        $this->addSql('ALTER TABLE post_like DROP FOREIGN KEY FK_653627B8A76ED395');
        $this->addSql('ALTER TABLE post_rating DROP FOREIGN KEY FK_E8ABC2474B89032C');
        $this->addSql('ALTER TABLE post_rating DROP FOREIGN KEY FK_E8ABC247A76ED395');
        $this->addSql('ALTER TABLE reactivation_request DROP FOREIGN KEY FK_1B8F23C8A76ED395');
        $this->addSql('DROP TABLE bot_interaction');
        $this->addSql('DROP TABLE chatbot_config');
        $this->addSql('DROP TABLE group_invitation');
        $this->addSql('DROP TABLE post_comment');
        $this->addSql('DROP TABLE post_like');
        $this->addSql('DROP TABLE post_rating');
        $this->addSql('DROP TABLE reactivation_request');
        $this->addSql('ALTER TABLE study_groups DROP FOREIGN KEY FK_5038A158B03A8386');
        $this->addSql('DROP INDEX idx_group_privacy ON study_groups');
        $this->addSql('DROP INDEX idx_group_created ON study_groups');
        $this->addSql('DROP INDEX idx_group_activity ON study_groups');
        $this->addSql('ALTER TABLE study_groups DROP subject, DROP updated_at, DROP last_activity');
        $this->addSql('ALTER TABLE study_groups ADD CONSTRAINT `FK_5038A158B03A8386` FOREIGN KEY (created_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE users ADD password VARCHAR(255) NOT NULL, ADD roles JSON NOT NULL, ADD full_name VARCHAR(255) NOT NULL, DROP nom, DROP prenom, DROP mot_de_passe, DROP role, DROP reset_token, DROP reset_token_expires_at, DROP last_activity_at, DROP pays, DROP telephone, DROP annees_experience, DROP face_descriptor, DROP discr, DROP age, DROP sexe, DROP etablissement, DROP niveau, DROP specialite, DROP niveau_enseignement, CHANGE statut user_type VARCHAR(50) NOT NULL, CHANGE date_inscription created_at DATETIME NOT NULL');
    }
}
