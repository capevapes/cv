<?php

class Infortis_Fortis_Model_System_Config_Source_Design_Tex_Names
{
    public function toOptionArray()
    {
		return array(
			array('value' => '0',			'label' => Mage::helper('fortis')->__('None')),
			
			array('value' => 'grain1',		'label' => Mage::helper('fortis')->__('grain1')),
			array('value' => 'grain2',		'label' => Mage::helper('fortis')->__('grain2')),
			array('value' => 'grain3',		'label' => Mage::helper('fortis')->__('grain3')),

			array('value' => '1', 'label' => Mage::helper('fortis')->__('1')),
            array('value' => '2', 'label' => Mage::helper('fortis')->__('2')),
            array('value' => '3', 'label' => Mage::helper('fortis')->__('3')),
			array('value' => '4', 'label' => Mage::helper('fortis')->__('4')),
			array('value' => '5', 'label' => Mage::helper('fortis')->__('5')),
			array('value' => '6', 'label' => Mage::helper('fortis')->__('6')),
			
			array('value' => 'white/1', 'label' => Mage::helper('fortis')->__('1 white')),
            array('value' => 'white/2', 'label' => Mage::helper('fortis')->__('2 white')),
            array('value' => 'white/3', 'label' => Mage::helper('fortis')->__('3 white')),
			array('value' => 'white/4', 'label' => Mage::helper('fortis')->__('4 white')),
			array('value' => 'white/5', 'label' => Mage::helper('fortis')->__('5 white')),
			array('value' => 'white/6', 'label' => Mage::helper('fortis')->__('6 white')),
			
			array('value' => 'custom1', 'label' => Mage::helper('fortis')->__('custom1')),
			array('value' => 'custom2', 'label' => Mage::helper('fortis')->__('custom2')),
			array('value' => 'custom3', 'label' => Mage::helper('fortis')->__('custom3')),
			array('value' => 'custom4', 'label' => Mage::helper('fortis')->__('custom4'))
        );
    }
}