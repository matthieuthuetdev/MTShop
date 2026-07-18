<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260718231000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add roles and password columns to users for manual sign-up authentication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD roles JSON NOT NULL DEFAULT '[]'");
        $this->addSql("ALTER TABLE users ADD password VARCHAR(255) NOT NULL DEFAULT ''");
        $this->addSql('ALTER TABLE users ALTER COLUMN roles DROP DEFAULT');
        $this->addSql('ALTER TABLE users ALTER COLUMN password DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN roles');
        $this->addSql('ALTER TABLE users DROP COLUMN password');
    }
}
