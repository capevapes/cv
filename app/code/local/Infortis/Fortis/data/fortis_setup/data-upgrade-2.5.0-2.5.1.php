<?php

$installer = $this;
$installer->startSetup();


Mage::getSingleton('fortis/cssgen_generator')->generateCss('grid',   NULL, NULL);
Mage::getSingleton('fortis/cssgen_generator')->generateCss('layout', NULL, NULL);
Mage::getSingleton('fortis/cssgen_generator')->generateCss('design', NULL, NULL);


$installer->endSetup();
