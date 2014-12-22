<?php

$installer = $this;
$installer->startSetup();


//Disable old home link, enable new
Mage::getConfig()->saveConfig('ultramegamenu/mainmenu/home_link_icon', '1');
Mage::getConfig()->saveConfig('ultramegamenu/mainmenu/home', '0');
Mage::getConfig()->saveConfig('ultramegamenu/mainmenu/home_img', '0');


$installer->endSetup();
