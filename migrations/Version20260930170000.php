<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add KPI goals for Statistics targets and progress tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE kpi_goals (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, created_by BIGINT UNSIGNED NOT NULL, metric VARCHAR(40) NOT NULL, direction VARCHAR(20) NOT NULL, target_value NUMERIC(20, 2) NOT NULL, initial_value NUMERIC(20, 2) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_KPI_GOAL_PERIOD (start_date, end_date), INDEX IDX_KPI_GOAL_METRIC (metric), INDEX IDX_KPI_GOAL_CREATED_BY (created_by), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE kpi_goals ADD CONSTRAINT FK_KPI_GOAL_CREATED_BY FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kpi_goals DROP FOREIGN KEY FK_KPI_GOAL_CREATED_BY');
        $this->addSql('DROP TABLE kpi_goals');
    }
}
