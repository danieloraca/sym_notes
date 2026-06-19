<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260619110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users table and assign notes to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_USERS_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE notes ADD owner_id INT NOT NULL');
        $this->addSql('ALTER TABLE notes ADD CONSTRAINT FK_NOTES_OWNER_ID FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_NOTES_OWNER_ID ON notes (owner_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notes DROP FOREIGN KEY FK_NOTES_OWNER_ID');
        $this->addSql('DROP INDEX IDX_NOTES_OWNER_ID ON notes');
        $this->addSql('ALTER TABLE notes DROP owner_id');
        $this->addSql('DROP TABLE users');
    }
}
