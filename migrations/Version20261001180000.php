<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional sales point scope to KPI goals.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE kpi_goals ADD sales_point_id BIGINT UNSIGNED DEFAULT NULL");
        $this->addSql("CREATE INDEX idx_kpi_goal_sales_point ON kpi_goals (sales_point_id)");
        $this->addSql("ALTER TABLE kpi_goals ADD CONSTRAINT FK_KPI_GOAL_SALES_POINT FOREIGN KEY (sales_point_id) REFERENCES sales_points (id) ON DELETE RESTRICT");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE kpi_goals DROP FOREIGN KEY FK_KPI_GOAL_SALES_POINT");
        $this->addSql("DROP INDEX idx_kpi_goal_sales_point ON kpi_goals");
        $this->addSql("ALTER TABLE kpi_goals DROP sales_point_id");
    }
}
