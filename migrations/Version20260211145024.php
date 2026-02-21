<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211145024 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE group_invitation (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, invited_at DATETIME NOT NULL, code VARCHAR(32) NOT NULL, status VARCHAR(10) NOT NULL, role VARCHAR(20) NOT NULL, responded_at DATETIME DEFAULT NULL, group_id INT NOT NULL, invited_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_26D0001077153098 (code), INDEX IDX_26D00010FE54D947 (group_id), INDEX IDX_26D00010A7B4A7E3 (invited_by_id), INDEX idx_invitation_email (email), INDEX idx_invitation_status (status), INDEX idx_invitation_code (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE group_member (id INT AUTO_INCREMENT NOT NULL, member_role VARCHAR(20) NOT NULL, joined_at DATETIME NOT NULL, group_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_A36222A8FE54D947 (group_id), INDEX idx_member_group_role (group_id, member_role), INDEX idx_member_user (user_id), INDEX idx_member_joined (joined_at), UNIQUE INDEX uniq_group_user (group_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE group_post (id INT AUTO_INCREMENT NOT NULL, post_type VARCHAR(10) NOT NULL, title VARCHAR(200) DEFAULT NULL, body LONGTEXT NOT NULL, attachment_url VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, group_id INT NOT NULL, author_id INT NOT NULL, parent_post_id INT DEFAULT NULL, INDEX IDX_73D037FDFE54D947 (group_id), INDEX IDX_73D037FD39C1776A (parent_post_id), INDEX idx_post_group_created (group_id, created_at), INDEX idx_post_author (author_id), INDEX idx_post_type (post_type), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post_comment (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL, post_id INT NOT NULL, author_id INT NOT NULL, parent_comment_id INT DEFAULT NULL, INDEX idx_comment_post (post_id), INDEX idx_comment_author (author_id), INDEX idx_comment_parent (parent_comment_id), INDEX idx_comment_created (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post_like (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, post_id INT NOT NULL, user_id INT NOT NULL, INDEX idx_like_post (post_id), INDEX idx_like_user (user_id), UNIQUE INDEX uniq_post_user_like (post_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post_rating (id INT AUTO_INCREMENT NOT NULL, rating SMALLINT NOT NULL, created_at DATETIME NOT NULL, post_id INT NOT NULL, user_id INT NOT NULL, INDEX idx_rating_post (post_id), INDEX idx_rating_user (user_id), UNIQUE INDEX uniq_post_user_rating (post_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE study_group (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, privacy VARCHAR(10) NOT NULL, subject VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, last_activity DATETIME DEFAULT NULL, created_by_id INT NOT NULL, INDEX IDX_32BA1425B03A8386 (created_by_id), INDEX idx_group_privacy (privacy), INDEX idx_group_created (created_at), INDEX idx_group_activity (last_activity), INDEX idx_group_subject (subject), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, mot_de_passe VARCHAR(255) NOT NULL, role VARCHAR(255) NOT NULL, statut VARCHAR(50) NOT NULL, date_inscription DATETIME NOT NULL, reset_token VARCHAR(255) DEFAULT NULL, reset_token_expires_at DATETIME DEFAULT NULL, last_activity_at DATETIME DEFAULT NULL, discr VARCHAR(255) NOT NULL, age INT DEFAULT NULL, sexe VARCHAR(10) DEFAULT NULL, etablissement VARCHAR(255) DEFAULT NULL, niveau VARCHAR(50) DEFAULT NULL, pays VARCHAR(2) DEFAULT NULL, specialite VARCHAR(255) DEFAULT NULL, niveau_enseignement VARCHAR(255) DEFAULT NULL, annees_experience INT DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE group_invitation ADD CONSTRAINT FK_26D00010FE54D947 FOREIGN KEY (group_id) REFERENCES study_group (id)');
        $this->addSql('ALTER TABLE group_invitation ADD CONSTRAINT FK_26D00010A7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE group_member ADD CONSTRAINT FK_A36222A8FE54D947 FOREIGN KEY (group_id) REFERENCES study_group (id)');
        $this->addSql('ALTER TABLE group_member ADD CONSTRAINT FK_A36222A8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE group_post ADD CONSTRAINT FK_73D037FDFE54D947 FOREIGN KEY (group_id) REFERENCES study_group (id)');
        $this->addSql('ALTER TABLE group_post ADD CONSTRAINT FK_73D037FDF675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE group_post ADD CONSTRAINT FK_73D037FD39C1776A FOREIGN KEY (parent_post_id) REFERENCES group_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_comment ADD CONSTRAINT FK_A99CE55F4B89032C FOREIGN KEY (post_id) REFERENCES group_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_comment ADD CONSTRAINT FK_A99CE55FF675F31B FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_comment ADD CONSTRAINT FK_A99CE55FBF2AF943 FOREIGN KEY (parent_comment_id) REFERENCES post_comment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_like ADD CONSTRAINT FK_653627B84B89032C FOREIGN KEY (post_id) REFERENCES group_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_like ADD CONSTRAINT FK_653627B8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_rating ADD CONSTRAINT FK_E8ABC2474B89032C FOREIGN KEY (post_id) REFERENCES group_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_rating ADD CONSTRAINT FK_E8ABC247A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE study_group ADD CONSTRAINT FK_32BA1425B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE group_invitation DROP FOREIGN KEY FK_26D00010FE54D947');
        $this->addSql('ALTER TABLE group_invitation DROP FOREIGN KEY FK_26D00010A7B4A7E3');
        $this->addSql('ALTER TABLE group_member DROP FOREIGN KEY FK_A36222A8FE54D947');
        $this->addSql('ALTER TABLE group_member DROP FOREIGN KEY FK_A36222A8A76ED395');
        $this->addSql('ALTER TABLE group_post DROP FOREIGN KEY FK_73D037FDFE54D947');
        $this->addSql('ALTER TABLE group_post DROP FOREIGN KEY FK_73D037FDF675F31B');
        $this->addSql('ALTER TABLE group_post DROP FOREIGN KEY FK_73D037FD39C1776A');
        $this->addSql('ALTER TABLE post_comment DROP FOREIGN KEY FK_A99CE55F4B89032C');
        $this->addSql('ALTER TABLE post_comment DROP FOREIGN KEY FK_A99CE55FF675F31B');
        $this->addSql('ALTER TABLE post_comment DROP FOREIGN KEY FK_A99CE55FBF2AF943');
        $this->addSql('ALTER TABLE post_like DROP FOREIGN KEY FK_653627B84B89032C');
        $this->addSql('ALTER TABLE post_like DROP FOREIGN KEY FK_653627B8A76ED395');
        $this->addSql('ALTER TABLE post_rating DROP FOREIGN KEY FK_E8ABC2474B89032C');
        $this->addSql('ALTER TABLE post_rating DROP FOREIGN KEY FK_E8ABC247A76ED395');
        $this->addSql('ALTER TABLE study_group DROP FOREIGN KEY FK_32BA1425B03A8386');
        $this->addSql('DROP TABLE group_invitation');
        $this->addSql('DROP TABLE group_member');
        $this->addSql('DROP TABLE group_post');
        $this->addSql('DROP TABLE post_comment');
        $this->addSql('DROP TABLE post_like');
        $this->addSql('DROP TABLE post_rating');
        $this->addSql('DROP TABLE study_group');
        $this->addSql('DROP TABLE user');
    }
}
