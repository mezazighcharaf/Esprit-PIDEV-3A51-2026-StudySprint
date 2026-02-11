<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour ajouter les tables de la fonctionnalité groups
 */
final class Version20260211105549 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add groups functionality tables (study_group, group_member, group_post, group_invitation, post_like, post_rating, post_comment)';
    }

    public function up(Schema $schema): void
    {
        // Table study_group
        $this->addSql('CREATE TABLE study_group (id INT AUTO_INCREMENT NOT NULL, created_by_id INT NOT NULL, name VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, privacy VARCHAR(10) NOT NULL, subject VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_activity DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_32BA1425B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Table group_member
        $this->addSql('CREATE TABLE group_member (id INT AUTO_INCREMENT NOT NULL, group_id INT NOT NULL, user_id INT NOT NULL, member_role VARCHAR(20) NOT NULL, joined_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_A36222A8FE54D947 (group_id), INDEX IDX_A36222A8A76ED395 (user_id), UNIQUE INDEX uniq_group_user (group_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Table group_post
        $this->addSql('CREATE TABLE group_post (id INT AUTO_INCREMENT NOT NULL, group_id INT NOT NULL, author_id INT NOT NULL, parent_post_id INT DEFAULT NULL, post_type VARCHAR(10) NOT NULL, title VARCHAR(200) DEFAULT NULL, body LONGTEXT NOT NULL, attachment_url VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_73D037FDFE54D947 (group_id), INDEX IDX_73D037FDF675F31B (author_id), INDEX IDX_73D037FD39C1776A (parent_post_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Table group_invitation
        $this->addSql('CREATE TABLE group_invitation (id INT AUTO_INCREMENT NOT NULL, group_id INT NOT NULL, invited_by_id INT DEFAULT NULL, email VARCHAR(255) NOT NULL, code VARCHAR(32) NOT NULL, status VARCHAR(10) NOT NULL, role VARCHAR(20) NOT NULL, invited_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', responded_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C4DA2D7FE54D947 (group_id), INDEX IDX_C4DA2D74D0C1FC (invited_by_id), UNIQUE INDEX UNIQ_C4DA2D777153098 (code), UNIQUE INDEX uniq_group_email (group_id, email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Table post_like
        $this->addSql('CREATE TABLE post_like (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, user_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_653627B84B89032C (post_id), INDEX IDX_653627B8A76ED395 (user_id), UNIQUE INDEX uniq_post_user_like (post_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Table post_rating
        $this->addSql('CREATE TABLE post_rating (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, user_id INT NOT NULL, rating SMALLINT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C6C4BCB84B89032C (post_id), INDEX IDX_C6C4BCB8A76ED395 (user_id), UNIQUE INDEX uniq_post_user_rating (post_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Table post_comment
        $this->addSql('CREATE TABLE post_comment (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, author_id INT NOT NULL, parent_comment_id INT DEFAULT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_A99CE55F4B89032C (post_id), INDEX IDX_A99CE55FF675F31B (author_id), INDEX IDX_A99CE55FBF2AF943 (parent_comment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Foreign keys
        $this->addSql('ALTER TABLE study_group ADD CONSTRAINT FK_32BA1425B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE group_member ADD CONSTRAINT FK_A36222A8FE54D947 FOREIGN KEY (group_id) REFERENCES study_group (id)');
        $this->addSql('ALTER TABLE group_member ADD CONSTRAINT FK_A36222A8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE group_post ADD CONSTRAINT FK_73D037FDFE54D947 FOREIGN KEY (group_id) REFERENCES study_group (id)');
        $this->addSql('ALTER TABLE group_post ADD CONSTRAINT FK_73D037FDF675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE group_post ADD CONSTRAINT FK_73D037FD39C1776A FOREIGN KEY (parent_post_id) REFERENCES group_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_invitation ADD CONSTRAINT FK_C4DA2D7FE54D947 FOREIGN KEY (group_id) REFERENCES study_group (id)');
        $this->addSql('ALTER TABLE group_invitation ADD CONSTRAINT FK_C4DA2D74D0C1FC FOREIGN KEY (invited_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE post_like ADD CONSTRAINT FK_653627B84B89032C FOREIGN KEY (post_id) REFERENCES group_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_like ADD CONSTRAINT FK_653627B8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_rating ADD CONSTRAINT FK_C6C4BCB84B89032C FOREIGN KEY (post_id) REFERENCES group_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_rating ADD CONSTRAINT FK_C6C4BCB8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_comment ADD CONSTRAINT FK_A99CE55F4B89032C FOREIGN KEY (post_id) REFERENCES group_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_comment ADD CONSTRAINT FK_A99CE55FF675F31B FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_comment ADD CONSTRAINT FK_A99CE55FBF2AF943 FOREIGN KEY (parent_comment_id) REFERENCES post_comment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign keys
        $this->addSql('ALTER TABLE study_group DROP FOREIGN KEY FK_32BA1425B03A8386');
        $this->addSql('ALTER TABLE group_member DROP FOREIGN KEY FK_A36222A8FE54D947');
        $this->addSql('ALTER TABLE group_member DROP FOREIGN KEY FK_A36222A8A76ED395');
        $this->addSql('ALTER TABLE group_post DROP FOREIGN KEY FK_73D037FDFE54D947');
        $this->addSql('ALTER TABLE group_post DROP FOREIGN KEY FK_73D037FDF675F31B');
        $this->addSql('ALTER TABLE group_post DROP FOREIGN KEY FK_73D037FD39C1776A');
        $this->addSql('ALTER TABLE group_invitation DROP FOREIGN KEY FK_C4DA2D7FE54D947');
        $this->addSql('ALTER TABLE group_invitation DROP FOREIGN KEY FK_C4DA2D74D0C1FC');
        $this->addSql('ALTER TABLE post_like DROP FOREIGN KEY FK_653627B84B89032C');
        $this->addSql('ALTER TABLE post_like DROP FOREIGN KEY FK_653627B8A76ED395');
        $this->addSql('ALTER TABLE post_rating DROP FOREIGN KEY FK_C6C4BCB84B89032C');
        $this->addSql('ALTER TABLE post_rating DROP FOREIGN KEY FK_C6C4BCB8A76ED395');
        $this->addSql('ALTER TABLE post_comment DROP FOREIGN KEY FK_A99CE55F4B89032C');
        $this->addSql('ALTER TABLE post_comment DROP FOREIGN KEY FK_A99CE55FF675F31B');
        $this->addSql('ALTER TABLE post_comment DROP FOREIGN KEY FK_A99CE55FBF2AF943');
        
        // Drop tables
        $this->addSql('DROP TABLE study_group');
        $this->addSql('DROP TABLE group_member');
        $this->addSql('DROP TABLE group_post');
        $this->addSql('DROP TABLE group_invitation');
        $this->addSql('DROP TABLE post_like');
        $this->addSql('DROP TABLE post_rating');
        $this->addSql('DROP TABLE post_comment');
    }
}
