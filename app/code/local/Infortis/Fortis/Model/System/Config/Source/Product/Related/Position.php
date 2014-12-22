<?php

class Infortis_Fortis_Model_System_Config_Source_Product_Related_Position
{
    public function toOptionArray()
    {
		return array(
			array('value' => '10',	'label' => Mage::helper('fortis')->__('Top of the Secondary Column (below brand logo)')),
			array('value' => '11',	'label' => Mage::helper('fortis')->__('Bottom of the Secondary Column')),
			array('value' => '20',	'label' => Mage::helper('fortis')->__('At the side of the tabs')),
        );
    }
}