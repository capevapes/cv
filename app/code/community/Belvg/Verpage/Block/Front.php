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
class Belvg_Verpage_Block_Front extends Mage_Core_Block_Template
{

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('belvg/verpage/block.phtml');
    }
    
    public function getLogo()
    {
        if (Mage::getStoreConfigFlag('verpage/window/logo_state')) {
            $src = Mage::getStoreConfig('verpage/window/logo');
            $width = Mage::getStoreConfig('verpage/window/width');
            $height = Mage::getStoreConfig('verpage/window/height');
            return '<img src="' . Mage::getBaseUrl('media') . 'verpage' . DS . $src . '" width="' . $width . '" height="' . $height . '"/>';
        } else {
            return FALSE;
        }
    }

    public function getText($n)
    {
        if (Mage::getStoreConfig('verpage/window/text' . $n . '_state')) {
            return Mage::getStoreConfig('verpage/window/text' . $n);
        } else {
            return FALSE;
        }
    }
    
    public function getVerSelect()
    {
        $helper = Mage::helper('verpage');
        if (Mage::getStoreConfig('verpage/window/buttons_state') == 'select') {
            $html = '<span>' . $helper->__('Day:') . '</span><select name="day" id="day">';
            for ($i=1; $i<=31; $i++) {
                $html .= '<option value="' . $i . '">' . $i . '</option>';
            }
            
            $html .= '</select><span>' . $helper->__('Month:') . '</span><select name="month" id="month">';
            for ($i=1; $i<=12; $i++) {
                $html .= '<option value="' . $i . '">' . $i . '</option>';
            }
            
            $html .= '</select><span>' . $helper->__('Year:') . '</span><select name="year" id="year">';
            $date = getDate();
            for ($i=1913; $i<=($date['year']-1); $i++) {
                $html .= '<option value="' . $i . '">' . $i . '</option>';
            }
            
            $html .= '<option selected value="' . $date['year'] . '">' . $date['year'] . '</option>';
            $html .= '</select>';
        } else {
            $html = FALSE;
        }
        
        return $html;
    }
    
    public function getLeaveText()
    {
        return Mage::getStoreConfig('verpage/window/leave');
    }
    
    public function getEnterText()
    {
        return Mage::getStoreConfig('verpage/window/enter');
    }
    
    public function getConfirmText()
    {
        if (Mage::getStoreConfigFlag('verpage/window/confirm_state')) {
            return Mage::getStoreConfig('verpage/window/confirm');
        } else {
            return FALSE;
        }
    }
    
    public function getLastUrl()
    {
        return Mage::getSingleton('core/session')->getLastUrl();
    }
    
    public function getStyles()
    {
        if (Mage::getStoreConfigFlag('verpage/advanced/styles_state')) {
            return Mage::getStoreConfig('verpage/advanced/styles');
        } else {
            return FALSE;
        }
    }
    
    public function getVerAge()
    {
        if ($age = (int)Mage::getStoreConfig('verpage/settings/age')) {
            return $age;
        }
        
        return 18;
    }
    
}