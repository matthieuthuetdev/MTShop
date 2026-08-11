<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260809143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Stripe identifiers to orders table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE orders ADD stripe_checkout_session_id VARCHAR(255) DEFAULT NULL, ADD stripe_payment_intent_id VARCHAR(255) DEFAULT NULL");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E52FFDEEA941848E ON orders (stripe_checkout_session_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E52FFDEE8091C28F ON orders (stripe_payment_intent_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_E52FFDEEA941848E ON orders');
        $this->addSql('DROP INDEX UNIQ_E52FFDEE8091C28F ON orders');
        $this->addSql('ALTER TABLE orders DROP stripe_checkout_session_id, DROP stripe_payment_intent_id');
    }
}
