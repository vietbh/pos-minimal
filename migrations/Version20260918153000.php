<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create and align session-first bank payment persistence.';
    }

    public function up(Schema $schema): void
    {
        $this->ensureCheckoutPaymentSessionsTable();
        $this->ensurePaymentReferencesTable();
    }

    public function down(Schema $schema): void
    {
        // Keep this migration non-destructive for existing payment data.
        // Rolling back application code must not delete payment references.
    }

    private function ensureCheckoutPaymentSessionsTable(): void
    {
        if (!$this->tableExists('checkout_payment_sessions')) {
            $this->addSql(
                'CREATE TABLE checkout_payment_sessions (
                    id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                    user_id BIGINT UNSIGNED NOT NULL,
                    customer_id BIGINT UNSIGNED DEFAULT NULL,
                    payment_bank_account_id BIGINT UNSIGNED NOT NULL,
                    cart_snapshot JSON NOT NULL,
                    amount NUMERIC(15, 2) NOT NULL,
                    note LONGTEXT DEFAULT NULL,
                    active_key VARCHAR(64) DEFAULT NULL,
                    status VARCHAR(40) NOT NULL,
                    order_id BIGINT UNSIGNED DEFAULT NULL,
                    created_at DATETIME NOT NULL,
                    expires_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE INDEX UNIQ_CHECKOUT_SESSION_ACTIVE_KEY (active_key),
                    INDEX idx_checkout_session_user_status (user_id, status),
                    PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
            );

            $this->addSql('ALTER TABLE checkout_payment_sessions ADD CONSTRAINT FK_CHECKOUT_SESSION_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT');
            $this->addSql('ALTER TABLE checkout_payment_sessions ADD CONSTRAINT FK_CHECKOUT_SESSION_CUSTOMER FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT');
            $this->addSql('ALTER TABLE checkout_payment_sessions ADD CONSTRAINT FK_CHECKOUT_SESSION_BANK_ACCOUNT FOREIGN KEY (payment_bank_account_id) REFERENCES payment_bank_accounts (id) ON DELETE RESTRICT');
            $this->addSql('ALTER TABLE checkout_payment_sessions ADD CONSTRAINT FK_CHECKOUT_SESSION_ORDER FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT');

            return;
        }

        $this->ensureColumn('checkout_payment_sessions', 'active_key', 'VARCHAR(64) DEFAULT NULL');
        $this->ensureColumn('checkout_payment_sessions', 'order_id', 'BIGINT UNSIGNED DEFAULT NULL');
        $this->ensureColumn('checkout_payment_sessions', 'cart_snapshot', 'JSON NOT NULL');
        $this->ensureColumn('checkout_payment_sessions', 'amount', 'NUMERIC(15, 2) NOT NULL');
        $this->ensureColumn('checkout_payment_sessions', 'note', 'LONGTEXT DEFAULT NULL');
        $this->ensureColumn('checkout_payment_sessions', 'status', 'VARCHAR(40) NOT NULL');
        $this->ensureColumn('checkout_payment_sessions', 'created_at', 'DATETIME NOT NULL');
        $this->ensureColumn('checkout_payment_sessions', 'expires_at', 'DATETIME NOT NULL');
        $this->ensureColumn('checkout_payment_sessions', 'updated_at', 'DATETIME NOT NULL');

        $this->ensureIndex(
            'checkout_payment_sessions',
            'UNIQ_CHECKOUT_SESSION_ACTIVE_KEY',
            'UNIQUE INDEX UNIQ_CHECKOUT_SESSION_ACTIVE_KEY (active_key)',
        );
        $this->ensureIndex(
            'checkout_payment_sessions',
            'idx_checkout_session_user_status',
            'INDEX idx_checkout_session_user_status (user_id, status)',
        );
    }

    private function ensurePaymentReferencesTable(): void
    {
        if (!$this->tableExists('payment_references')) {
            $this->addSql(
                'CREATE TABLE payment_references (
                    id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                    reference VARCHAR(40) NOT NULL,
                    order_id BIGINT UNSIGNED DEFAULT NULL,
                    checkout_payment_session_id BIGINT UNSIGNED NOT NULL,
                    payment_id BIGINT UNSIGNED DEFAULT NULL,
                    payment_bank_account_id BIGINT UNSIGNED NOT NULL,
                    amount NUMERIC(15, 2) NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    matched_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE INDEX UNIQ_PAYMENT_REFERENCES_REFERENCE (reference),
                    INDEX idx_payment_reference_order (order_id),
                    INDEX idx_payment_reference_session (checkout_payment_session_id),
                    INDEX idx_payment_reference_status_expires (status, expires_at),
                    PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
            );

            $this->addSql('ALTER TABLE payment_references ADD CONSTRAINT FK_PAYMENT_REFERENCE_ORDER FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT');
            $this->addSql('ALTER TABLE payment_references ADD CONSTRAINT FK_PAYMENT_REFERENCE_SESSION FOREIGN KEY (checkout_payment_session_id) REFERENCES checkout_payment_sessions (id) ON DELETE RESTRICT');
            $this->addSql('ALTER TABLE payment_references ADD CONSTRAINT FK_PAYMENT_REFERENCE_PAYMENT FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE RESTRICT');
            $this->addSql('ALTER TABLE payment_references ADD CONSTRAINT FK_PAYMENT_REFERENCE_BANK_ACCOUNT FOREIGN KEY (payment_bank_account_id) REFERENCES payment_bank_accounts (id) ON DELETE RESTRICT');

            return;
        }

        if ($this->columnExists('payment_references', 'order_id')) {
            $this->addSql('ALTER TABLE payment_references MODIFY order_id BIGINT UNSIGNED DEFAULT NULL');
        }

        if ($this->columnExists('payment_references', 'payment_id')) {
            $this->addSql('ALTER TABLE payment_references MODIFY payment_id BIGINT UNSIGNED DEFAULT NULL');
        } else {
            $this->addSql('ALTER TABLE payment_references ADD payment_id BIGINT UNSIGNED DEFAULT NULL');
        }

        if (!$this->columnExists('payment_references', 'checkout_payment_session_id')) {
            $this->addSql('ALTER TABLE payment_references ADD checkout_payment_session_id BIGINT UNSIGNED DEFAULT NULL');
        }

        $this->ensureColumn('payment_references', 'payment_bank_account_id', 'BIGINT UNSIGNED NOT NULL');
        $this->ensureColumn('payment_references', 'amount', 'NUMERIC(15, 2) NOT NULL');
        $this->ensureColumn('payment_references', 'status', 'VARCHAR(20) NOT NULL');
        $this->ensureColumn('payment_references', 'expires_at', 'DATETIME NOT NULL');
        $this->ensureColumn('payment_references', 'matched_at', 'DATETIME DEFAULT NULL');
        $this->ensureColumn('payment_references', 'created_at', 'DATETIME NOT NULL');
        $this->ensureColumn('payment_references', 'updated_at', 'DATETIME NOT NULL');

        $this->ensureIndex(
            'payment_references',
            'UNIQ_PAYMENT_REFERENCES_REFERENCE',
            'UNIQUE INDEX UNIQ_PAYMENT_REFERENCES_REFERENCE (reference)',
        );
        $this->ensureIndex(
            'payment_references',
            'idx_payment_reference_order',
            'INDEX idx_payment_reference_order (order_id)',
        );
        $this->ensureIndex(
            'payment_references',
            'idx_payment_reference_session',
            'INDEX idx_payment_reference_session (checkout_payment_session_id)',
        );
        $this->ensureIndex(
            'payment_references',
            'idx_payment_reference_status_expires',
            'INDEX idx_payment_reference_status_expires (status, expires_at)',
        );

        $this->ensureForeignKey(
            'payment_references',
            'FK_PAYMENT_REFERENCE_SESSION',
            'checkout_payment_session_id',
            'checkout_payment_sessions',
            'id',
        );
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        if (!$this->columnExists($table, $column)) {
            $this->addSql(sprintf('ALTER TABLE %s ADD %s %s', $table, $column, $definition));
        }
    }

    private function ensureIndex(string $table, string $indexName, string $definition): void
    {
        if (!$this->indexExists($table, $indexName)) {
            $this->addSql(sprintf('ALTER TABLE %s ADD %s', $table, $definition));
        }
    }

    private function ensureForeignKey(
        string $table,
        string $constraintName,
        string $column,
        string $referencedTable,
        string $referencedColumn,
    ): void {
        if ($this->foreignKeyExists($table, $constraintName)) {
            return;
        }

        $this->addSql(sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE RESTRICT',
            $table,
            $constraintName,
            $column,
            $referencedTable,
            $referencedColumn,
        ));
    }

    private function tableExists(string $table): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table],
        ) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column],
        ) > 0;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $indexName],
        ) > 0;
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = \'FOREIGN KEY\'',
            [$table, $constraintName],
        ) > 0;
    }
}
