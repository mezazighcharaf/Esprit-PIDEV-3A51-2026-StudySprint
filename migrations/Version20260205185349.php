<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260205185349 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ai_generation_logs ADD user_feedback SMALLINT DEFAULT NULL, ADD idempotency_key VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE chapters ADD ai_summary LONGTEXT DEFAULT NULL, ADD ai_key_points JSON DEFAULT NULL, ADD ai_tags JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE group_posts ADD ai_summary LONGTEXT DEFAULT NULL, ADD ai_category VARCHAR(100) DEFAULT NULL, ADD ai_tags JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE user_profiles ADD ai_suggested_bio LONGTEXT DEFAULT NULL, ADD ai_suggested_goals LONGTEXT DEFAULT NULL, ADD ai_suggested_routine LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ai_generation_logs DROP user_feedback, DROP idempotency_key');
        $this->addSql('ALTER TABLE chapters DROP ai_summary, DROP ai_key_points, DROP ai_tags');
        $this->addSql('ALTER TABLE group_posts DROP ai_summary, DROP ai_category, DROP ai_tags');
        $this->addSql('ALTER TABLE user_profiles DROP ai_suggested_bio, DROP ai_suggested_goals, DROP ai_suggested_routine');
    }
}
