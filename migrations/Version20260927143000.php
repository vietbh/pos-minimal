<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927143000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add Product and Stock Excel import batches, row states and row errors.'; }
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE import_batches (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, requested_by_user_id BIGINT UNSIGNED NOT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(30) NOT NULL, original_filename VARCHAR(255) NOT NULL, storage_key VARCHAR(500) NOT NULL, template_version VARCHAR(30) NOT NULL, request_id VARCHAR(100) DEFAULT NULL, total_rows INT NOT NULL, valid_rows INT NOT NULL, invalid_rows INT NOT NULL, processed_rows INT NOT NULL, success_rows INT NOT NULL, failed_rows INT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', started_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', finished_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_import_batch_status_created (status, created_at), INDEX idx_import_batch_user_created (requested_by_user_id, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE import_batches ADD CONSTRAINT FK_IMPORT_BATCH_USER FOREIGN KEY (requested_by_user_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql("CREATE TABLE import_row_states (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, import_batch_id BIGINT UNSIGNED NOT NULL, row_num INT NOT NULL, fingerprint VARCHAR(64) NOT NULL, status VARCHAR(20) NOT NULL, result_id BIGINT DEFAULT NULL, error_code VARCHAR(100) DEFAULT NULL, error_message VARCHAR(500) DEFAULT NULL, started_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', finished_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uq_import_row_batch_row (import_batch_id, row_num), INDEX idx_import_row_batch_status (import_batch_id, status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE import_row_states ADD CONSTRAINT FK_IMPORT_ROW_BATCH FOREIGN KEY (import_batch_id) REFERENCES import_batches (id) ON DELETE CASCADE');
        $this->addSql("CREATE TABLE import_row_errors (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, import_batch_id BIGINT UNSIGNED NOT NULL, row_num INT NOT NULL, field VARCHAR(100) NOT NULL, error_code VARCHAR(100) NOT NULL, message VARCHAR(500) NOT NULL, raw_value LONGTEXT DEFAULT NULL, INDEX idx_import_error_batch_row (import_batch_id, row_num), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE import_row_errors ADD CONSTRAINT FK_IMPORT_ERROR_BATCH FOREIGN KEY (import_batch_id) REFERENCES import_batches (id) ON DELETE CASCADE');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_row_errors DROP FOREIGN KEY FK_IMPORT_ERROR_BATCH');
        $this->addSql('ALTER TABLE import_row_states DROP FOREIGN KEY FK_IMPORT_ROW_BATCH');
        $this->addSql('ALTER TABLE import_batches DROP FOREIGN KEY FK_IMPORT_BATCH_USER');
        $this->addSql('DROP TABLE import_row_errors');
        $this->addSql('DROP TABLE import_row_states');
        $this->addSql('DROP TABLE import_batches');
    }
}
