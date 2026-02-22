<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222012555 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bot_interaction (id INT AUTO_INCREMENT NOT NULL, question LONGTEXT NOT NULL, response LONGTEXT NOT NULL, tokens_used INT DEFAULT 0 NOT NULL, response_time_ms INT DEFAULT NULL, feedback VARCHAR(15) DEFAULT NULL, created_at DATETIME NOT NULL, group_id INT NOT NULL, post_id INT NOT NULL, comment_id INT DEFAULT NULL, triggered_by_id INT NOT NULL, INDEX IDX_8F8A536EFE54D947 (group_id), INDEX IDX_8F8A536EF8697D13 (comment_id), INDEX idx_bot_group_created (group_id, created_at), INDEX idx_bot_triggered_by (triggered_by_id), INDEX idx_bot_post (post_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE chatbot_config (id INT AUTO_INCREMENT NOT NULL, is_enabled TINYINT(1) DEFAULT 1 NOT NULL, bot_name VARCHAR(50) DEFAULT \'StudyBot\' NOT NULL, personality VARCHAR(20) DEFAULT \'tutor\' NOT NULL, subject_context VARCHAR(200) DEFAULT NULL, trigger_mode VARCHAR(20) DEFAULT \'mention\' NOT NULL, trigger_keywords JSON NOT NULL, max_response_length INT DEFAULT 500 NOT NULL, language VARCHAR(5) DEFAULT \'fr\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, group_id INT NOT NULL, UNIQUE INDEX uniq_chatbot_group (group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE bot_interaction ADD CONSTRAINT FK_8F8A536EFE54D947 FOREIGN KEY (group_id) REFERENCES study_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bot_interaction ADD CONSTRAINT FK_8F8A536E4B89032C FOREIGN KEY (post_id) REFERENCES group_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bot_interaction ADD CONSTRAINT FK_8F8A536EF8697D13 FOREIGN KEY (comment_id) REFERENCES post_comment (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE bot_interaction ADD CONSTRAINT FK_8F8A536E63C5923F FOREIGN KEY (triggered_by_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chatbot_config ADD CONSTRAINT FK_46FCC9C3FE54D947 FOREIGN KEY (group_id) REFERENCES study_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_comment ADD is_bot TINYINT(1) DEFAULT 0 NOT NULL, ADD bot_name VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bot_interaction DROP FOREIGN KEY FK_8F8A536EFE54D947');
        $this->addSql('ALTER TABLE bot_interaction DROP FOREIGN KEY FK_8F8A536E4B89032C');
        $this->addSql('ALTER TABLE bot_interaction DROP FOREIGN KEY FK_8F8A536EF8697D13');
        $this->addSql('ALTER TABLE bot_interaction DROP FOREIGN KEY FK_8F8A536E63C5923F');
        $this->addSql('ALTER TABLE chatbot_config DROP FOREIGN KEY FK_46FCC9C3FE54D947');
        $this->addSql('DROP TABLE bot_interaction');
        $this->addSql('DROP TABLE chatbot_config');
        $this->addSql('ALTER TABLE post_comment DROP is_bot, DROP bot_name');
    }
}
