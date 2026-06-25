<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260625191856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cleaning_request ADD google_event_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE cleaning_request ADD last_sync_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE cleaning_request ADD sync_source VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE cleaning_request ADD sync_status VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE cleaning_request ADD needs_confirmation BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE cleaning_request ALTER status TYPE VARCHAR(40)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cleaning_request DROP google_event_id');
        $this->addSql('ALTER TABLE cleaning_request DROP last_sync_at');
        $this->addSql('ALTER TABLE cleaning_request DROP sync_source');
        $this->addSql('ALTER TABLE cleaning_request DROP sync_status');
        $this->addSql('ALTER TABLE cleaning_request DROP needs_confirmation');
        $this->addSql('ALTER TABLE cleaning_request ALTER status TYPE VARCHAR(20)');
    }
}
