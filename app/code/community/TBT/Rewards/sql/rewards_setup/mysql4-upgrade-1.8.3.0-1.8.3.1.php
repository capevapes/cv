<?php

$installer = $this;

$installer->startSetup();

$installer->attemptQuery("
    CREATE TABLE IF NOT EXISTS `{$this->getTable ('rewards/importer' )}` (
        `importer_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `type` varchar(255) NOT NULL DEFAULT '',
        `status` int(11) unsigned NOT NULL DEFAULT '0',
        `file` varchar(1023) DEFAULT NULL,
        `original_filename` varchar(255) DEFAULT NULL,
        `email` varchar(255) DEFAULT NULL,
        `options_json` varchar(1023) DEFAULT NULL,
        `count_total` int(11) unsigned DEFAULT NULL,
        `count_processed` int(11) unsigned NOT NULL DEFAULT '0',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `started_at` timestamp NULL DEFAULT NULL,
        `ended_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`importer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
);

// Clear cache.
$installer->prepareForDb();

$installer->endSetup();
