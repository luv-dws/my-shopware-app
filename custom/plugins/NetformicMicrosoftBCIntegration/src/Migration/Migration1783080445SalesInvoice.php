<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Migration step to create the netformic_bc_sales_invoice table.
 * This table stores imported sales invoice headers from Business Central.
 *
 * @internal
 */
class Migration1783080445SalesInvoice extends MigrationStep
{
    /**
     * Executes the get creation timestamp operation.
     *
     * @internal
     */
    public function getCreationTimestamp(): int
    {
        return 1783080445;
    }

    /**
     * Executes the update operation.
     *
     * @internal
     */
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `netformic_bc_sales_invoice` (
                `id` BINARY(16) NOT NULL,
                `system_id` VARCHAR(36) NULL,
                `number` VARCHAR(50) NULL,
                `external_document_number` VARCHAR(50) NULL,
                `invoice_date` DATE NULL,
                `due_date` DATE NULL,
                `customer_id` BINARY(16) NULL,
                `order_id` BINARY(16) NULL,
                `currency_code` VARCHAR(10) NULL,
                `total_amount_including_tax` FLOAT NULL,
                `discount_amount` FLOAT NULL,
                `salesperson` VARCHAR(20) NULL,
                `payment_terms_id` VARCHAR(36) NULL,
                `shipment_method_id` VARCHAR(36) NULL,
                `status` VARCHAR(50) NULL,
                `remaining_amount` FLOAT NULL,
                `bill_to_name` VARCHAR(100) NULL,
                `bill_to_address_line_1` VARCHAR(100) NULL,
                `bill_to_address_line_2` VARCHAR(100) NULL,
                `bill_to_city` VARCHAR(100) NULL,
                `bill_to_country` VARCHAR(3) NULL,
                `bill_to_state` VARCHAR(10) NULL,
                `bill_to_post_code` VARCHAR(20) NULL,
                `ship_to_name` VARCHAR(100) NULL,
                `ship_to_contact` VARCHAR(100) NULL,
                `ship_to_address_line_1` VARCHAR(100) NULL,
                `ship_to_address_line_2` VARCHAR(100) NULL,
                `ship_to_city` VARCHAR(100) NULL,
                `ship_to_country` VARCHAR(3) NULL,
                `ship_to_state` VARCHAR(10) NULL,
                `ship_to_post_code` VARCHAR(20) NULL,
                `sell_to_address_line_1` VARCHAR(100) NULL,
                `sell_to_address_line_2` VARCHAR(100) NULL,
                `sell_to_city` VARCHAR(100) NULL,
                `sell_to_country` VARCHAR(3) NULL,
                `sell_to_state` VARCHAR(10) NULL,
                `sell_to_post_code` VARCHAR(20) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.netformic_bc_sales_invoice.customer_id` FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.netformic_bc_sales_invoice.order_id` FOREIGN KEY (`order_id`)
                    REFERENCES `order` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
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
        $connection->executeStatement('DROP TABLE IF EXISTS `netformic_bc_sales_invoice`');
    }
}
