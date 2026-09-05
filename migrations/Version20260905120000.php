<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add file attachments to notes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE note_attachments (id INT AUTO_INCREMENT NOT NULL, note_id INT NOT NULL, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(255) NOT NULL, mime_type VARCHAR(255) NOT NULL, size INT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_617D6D531185AF6A (stored_name), INDEX IDX_617D6D5326ED0855 (note_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE note_attachments ADD CONSTRAINT FK_NOTE_ATTACHMENTS_NOTE_ID FOREIGN KEY (note_id) REFERENCES notes (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE note_attachments');
    }
}
