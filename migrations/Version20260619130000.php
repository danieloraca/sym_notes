<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260619130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add folders and assign notes to folders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE folders (id INT AUTO_INCREMENT NOT NULL, owner_id INT NOT NULL, parent_id INT DEFAULT NULL, name VARCHAR(120) NOT NULL, sort_position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_FOLDERS_OWNER_ID (owner_id), INDEX IDX_FOLDERS_PARENT_ID (parent_id), UNIQUE INDEX UNIQ_FOLDERS_OWNER_PARENT_NAME (owner_id, parent_id, name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE folders ADD CONSTRAINT FK_FOLDERS_OWNER_ID FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE folders ADD CONSTRAINT FK_FOLDERS_PARENT_ID FOREIGN KEY (parent_id) REFERENCES folders (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notes ADD folder_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE notes ADD CONSTRAINT FK_NOTES_FOLDER_ID FOREIGN KEY (folder_id) REFERENCES folders (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_NOTES_FOLDER_ID ON notes (folder_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notes DROP FOREIGN KEY FK_NOTES_FOLDER_ID');
        $this->addSql('DROP INDEX IDX_NOTES_FOLDER_ID ON notes');
        $this->addSql('ALTER TABLE notes DROP folder_id');
        $this->addSql('ALTER TABLE folders DROP FOREIGN KEY FK_FOLDERS_OWNER_ID');
        $this->addSql('ALTER TABLE folders DROP FOREIGN KEY FK_FOLDERS_PARENT_ID');
        $this->addSql('DROP TABLE folders');
    }
}
