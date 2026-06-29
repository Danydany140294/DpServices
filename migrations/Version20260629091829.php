<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260629091829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sync_log DROP CONSTRAINT fk_3171117696294493');
        $this->addSql('ALTER TABLE sync_log ADD CONSTRAINT FK_3171117696294493 FOREIGN KEY (cleaning_request_id) REFERENCES cleaning_request (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sync_log DROP CONSTRAINT FK_3171117696294493');
        $this->addSql('ALTER TABLE sync_log ADD CONSTRAINT fk_3171117696294493 FOREIGN KEY (cleaning_request_id) REFERENCES cleaning_request (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
