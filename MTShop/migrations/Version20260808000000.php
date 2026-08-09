<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260808000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add password reset fields to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD password_reset_token VARCHAR(255) DEFAULT NULL");
        $this->addSql('ALTER TABLE users ADD password_reset_approved TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql("ALTER TABLE users ADD password_reset_requested_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql("ALTER TABLE users ADD password_reset_approved_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql("ALTER TABLE users ADD password_reset_token_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP password_reset_token');
        $this->addSql('ALTER TABLE users DROP password_reset_approved');
        $this->addSql('ALTER TABLE users DROP password_reset_requested_at');
        $this->addSql('ALTER TABLE users DROP password_reset_approved_at');
        $this->addSql('ALTER TABLE users DROP password_reset_token_expires_at');
    }
}
