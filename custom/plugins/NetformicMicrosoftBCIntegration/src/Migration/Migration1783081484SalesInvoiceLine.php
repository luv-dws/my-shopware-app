<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Migration step to create the netformic_bc_sales_invoice_line table.
 * This table stores individual line items for imported sales invoices from Business Central.
 *
 * @internal
 */
class Migration1783081484SalesInvoiceLine extends MigrationStep
{
    /**
     * Executes the get creation timestamp operation.
     *
     * @internal
     */
    public function getCreationTimestamp(): int
    {
        return 1783081484;
    }

    /**
     * Executes the update operation.
     *
     * @internal
     */
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `netformic_bc_sales_invoice_line` (
                `id` BINARY(16) NOT NULL,
                `invoice_id` BINARY(16) NOT NULL,
                `system_id` VARCHAR(36) NULL,
                `product_id` BINARY(16) NULL,
                `item_number` VARCHAR(50) NULL,
                `description` VARCHAR(255) NULL,
                `unit_price` FLOAT NULL,
                `quantity` FLOAT NULL,
                `unit_of_measure_code` VARCHAR(20) NULL,
                `net_amount_including_tax` FLOAT NULL,
                `shipment_date` DATE NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.netformic_bc_sales_invoice_line.invoice_id` FOREIGN KEY (`invoice_id`)
                    REFERENCES `netformic_bc_sales_invoice` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.netformic_bc_sales_invoice_line.product_id` FOREIGN KEY (`product_id`)
                    REFERENCES `product` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    /**
     * Executes the update destructive operation.
     *
     * @internal
     */
    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `netformic_bc_sales_invoice_line`');
    }
}
