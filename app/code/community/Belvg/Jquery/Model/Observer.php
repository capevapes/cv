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
 * @package    Belvg_jQuery
 * @version    2.0.3.1
 * @copyright  Copyright (c) 2010 - 2012 BelVG LLC. (http://www.belvg.com)
 * @license    http://store.belvg.com/BelVG-LICENSE-COMMUNITY.txt
 */
class Belvg_Jquery_Model_Observer
{
    public function addLibz()
    {
        Mage::dispatchEvent('belvg_jquery_start', array());
        $jqueryHead = Mage::app()->getLayout()->getBlock('jquery_head');
        $head = Mage::app()->getLayout()->getBlock('head');
        if ($jqueryHead instanceof Belvg_Jquery_Block_Head 
        && is_object($head)
        && Mage::getStoreConfigFlag('jquery/settings/enabled')) {
            Mage::dispatchEvent('belvg_jquery_dispatch_before', array('jquery_head' => $jqueryHead));
            $helper = Mage::helper('jquery/data');
            $data = $head->getData();
            $tmp = $data['items'];
            $data['items'] = '';
            $head->setData($data);
            $libz = $jqueryHead->getLibz();
            $urlz = $jqueryHead->getJsUrlz();
            if (!empty($libz)) {
                foreach ($libz as $lib) {
                    $head->addJs($helper->getUrl($lib));
                }
            }
            
            if (!empty($urlz)) {
                foreach ($urlz as $url) {
                    $head->addJs($url);
                }
            }
            
            $data = $head->getData();
            if (!$data['items']) {
                $data['items'] = array();
            }
            
            $data['items'] = array_merge((array)$data['items'], (array)$tmp);
            $head->setData($data);
        }
    }
}