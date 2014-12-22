<?php

class Infortis_Fortis_Model_System_Config_Source_Header_Toplinks_Iconstyle
{
    public function toOptionArray()
    {
        return array(
            array('value' => 'um-no-icons',         'label' => Mage::helper('fortis')->__('Label')),
            array('value' => 'um-icons',            'label' => Mage::helper('fortis')->__('Icon')),
            //array('value' => 'um-icons-label',      'label' => Mage::helper('fortis')->__('Icon and Label')),
            array('value' => 'um-icons-label-top',  'label' => Mage::helper('fortis')->__('Icon Above Label')),
        );
    }
}