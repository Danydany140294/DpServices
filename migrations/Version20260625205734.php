<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260625205734 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cleaning_request ADD pending_scheduled_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE cleaning_request ADD pending_scheduled_time TIME(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE cleaning_request ADD pending_comment TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE cleaning_request ADD sync_in_progress BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cleaning_request DROP pending_scheduled_date');
        $this->addSql('ALTER TABLE cleaning_request DROP pending_scheduled_time');
        $this->addSql('ALTER TABLE cleaning_request DROP pending_comment');
        $this->addSql('ALTER TABLE cleaning_request DROP sync_in_progress');
    }
}
