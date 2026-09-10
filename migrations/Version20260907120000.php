<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add refunded/reversed lifecycle states and immutable order financial reversals.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE orders CHANGE status status VARCHAR(30) NOT NULL");
        $this->addSql("ALTER TABLE debts CHANGE status status VARCHAR(30) NOT NULL");
        $this->addSql("CREATE TABLE order_financial_reversals (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, amount NUMERIC(15, 2) NOT NULL, type VARCHAR(30) NOT NULL, reason VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, order_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_ORDER_FINANCIAL_REVERSAL_ORDER (order_id), INDEX IDX_ORDER_FINANCIAL_REVERSAL_TYPE_CREATED (type, created_at), INDEX IDX_ORDER_FINANCIAL_REVERSAL_USER (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("ALTER TABLE order_financial_reversals ADD CONSTRAINT FK_ORDER_FINANCIAL_REVERSAL_ORDER FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT");
        $this->addSql("ALTER TABLE order_financial_reversals ADD CONSTRAINT FK_ORDER_FINANCIAL_REVERSAL_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE order_financial_reversals');
    }
}
