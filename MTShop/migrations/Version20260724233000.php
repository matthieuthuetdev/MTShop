<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260724233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add delivery fields to orders for checkout summary and confirmation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE orders ADD delivery_method VARCHAR(50) NOT NULL DEFAULT 'basic', ADD delivery_fee NUMERIC(10, 2) NOT NULL DEFAULT 0, CHANGE payment_method payment_method VARCHAR(100) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP delivery_method, DROP delivery_fee');
    }
}
