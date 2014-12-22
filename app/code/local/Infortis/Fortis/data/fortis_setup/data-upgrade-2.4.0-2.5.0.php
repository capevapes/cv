<?php

$installer = $this;
$installer->startSetup();


Mage::getConfig()->saveConfig('ultramegamenu/mainmenu/spill', '0'); //Disable 

Mage::getConfig()->saveConfig('ultraslideshow/general/margin_bottom', '20'); //Set bottom margin below slideshow
Mage::getConfig()->saveConfig('ultraslideshow/general/position1', true); //Set full width slideshow
Mage::getConfig()->saveConfig('ultraslideshow/general/position2', false); //Set full width slideshow
Mage::getConfig()->saveConfig('ultraslideshow/general/blocks', 'block_slide_wide1,block_slide_wide2'); //Set wide sample slides
Mage::getConfig()->saveConfig('ultraslideshow/banners/banners', ' '); //Disable side banners


$installer->endSetup();
