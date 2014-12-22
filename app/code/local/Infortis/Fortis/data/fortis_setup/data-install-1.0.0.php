<?php
/**
 * @author		Infortis
 * @copyright	Copyright 2012 - 2013 Infortis
 */
$installer = $this;
$installer->startSetup();

//WYSIWYG hidden by default
Mage::getConfig()->saveConfig('cms/wysiwyg/enabled', 'hidden');

Mage::getSingleton('fortis/cssgen_generator')->generateCss('grid',   NULL, NULL);
Mage::getSingleton('fortis/cssgen_generator')->generateCss('layout', NULL, NULL);
Mage::getSingleton('fortis/cssgen_generator')->generateCss('design', NULL, NULL);

$installer->endSetup();
