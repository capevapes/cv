<?php

class Infortis_Fortis_Model_System_Config_Source_Category_AltImageColumn
{
    public function toOptionArray()
    {
        return array(
			array('value' => 'label',			'label' => Mage::helper('fortis')->__('Label')),
            array('value' => 'position',		'label' => Mage::helper('fortis')->__('Sort Order'))
        );
    }
}