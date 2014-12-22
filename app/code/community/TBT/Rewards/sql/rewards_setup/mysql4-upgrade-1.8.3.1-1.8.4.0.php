<?php

$installer = $this;

$installer->startSetup();

$installer->attemptQuery("
    ALTER TABLE `{$this->getTable('sales_flat_quote')}`
    CHANGE COLUMN `rewards_discount_base_tax_amount`
    `rewards_base_discount_tax_amount` DECIMAL(12,4) DEFAULT NULL;
");

// Clear cache.
$installer->prepareForDb();

$installer->endSetup();
