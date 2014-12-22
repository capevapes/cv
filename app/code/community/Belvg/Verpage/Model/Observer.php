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
class Belvg_Verpage_Model_Observer
{
    
    public function verificate(Varien_Event_Observer $observer)
    {
        if (Mage::getStoreConfigFlag('verpage/settings/enabled')) {
            $popup = FALSE;
            if (Mage::getStoreConfigFlag('verpage/settings/global')) {
                $popup = TRUE;
            }
            
            if (Mage::app()->getRequest()->getControllerName() == 'product') { 
                $product = Mage::registry('product')->getVerpageCatalogProduct();
                if ($product) {
                    $popup = TRUE;
                }
            }
            
            if (Mage::app()->getRequest()->getControllerName() == 'category') { 
                $category = Mage::registry('current_category')->getVerpageCatalogCategory();                                              
                if ($category) {
                    $popup = TRUE;
                }
            }
            
            if ($popup) {
                $this->_activateVerBlock($observer);
            }
        }
    }
    
    protected function _activateVerBlock($observer)
    {
        if ($block = Mage::app()->getLayout()->getBlock('after_body_start')) {
            $addBlock = Mage::app()->getLayout()->createBlock('verpage/front', 'ver_page_block');
            $block->append($addBlock);
        }
        
        if ($head = Mage::app()->getLayout()->getBlock('head')) {
            $head->addCss('css/belvg/verpage.css');
        }
        
        $observer->getEvent()->getJqueryHead()
                 ->addJs('belvg/verpage/jquery.nyroModal.custom.min.js')
                 ->addJs('belvg/verpage/verpage.js');
    }
    
}