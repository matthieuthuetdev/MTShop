<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate user roles from JSON array to enum-backed role column and default buyer for new users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD role VARCHAR(20) NOT NULL DEFAULT 'BUYER'");
        $this->addSql("UPDATE users SET role = 'ADMIN' WHERE JSON_CONTAINS(roles, '\"ROLE_ADMIN\"')");
        $this->addSql("UPDATE users SET role = 'SELLER' WHERE role = 'BUYER' AND JSON_CONTAINS(roles, '\"ROLE_SELLER\"')");
        $this->addSql('ALTER TABLE users DROP roles');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD roles JSON NOT NULL DEFAULT '[]'");
        $this->addSql("UPDATE users SET roles = JSON_ARRAY('ROLE_USER', CONCAT('ROLE_', role))");
        $this->addSql('ALTER TABLE users DROP role');
    }
}
