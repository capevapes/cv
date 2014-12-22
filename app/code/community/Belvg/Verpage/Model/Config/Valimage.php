<?php
/**
 * BelVG LLC.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://store.belvg.com/BelVG-LICENSE-COMMUNITY.txt
 *
 *******************************************************************
 * @category   Belvg
 * @package    Belvg_Verpage
 * @version    1.0.2
 * @copyright  Copyright (c) 2010 - 2013 BelVG LLC. (http://www.belvg.com)
 * @license    http://store.belvg.com/BelVG-LICENSE-COMMUNITY.txt
 */
class Belvg_Verpage_Model_Config_Valimage extends Mage_Core_Model_Config_Data
{
    
    const MAXSIZE = 500;
    const MINSIZE = 1;
    
    /**
     * Save value in module's config
     * @return Mage_Core_Model_Config_Data
     */  
    public function save()
    {
        $helper = Mage::helper('verpage');
        $int = (int)$this->getValue();
        if (($int > self::MAXSIZE) || !$int || ($int < self::MINSIZE)) {
            Mage::throwException($helper->__('Image size must be between %s and %s pixels', self::MINSIZE, self::MAXSIZE));
        }
        
        return parent::save();
    }
    
}