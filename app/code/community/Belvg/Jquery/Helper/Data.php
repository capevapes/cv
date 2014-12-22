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
class Belvg_Jquery_Helper_Data extends Mage_Core_Helper_Data
{
    public function getUrl($lib)
    {
        $return = '';
        switch ($lib) {
            case 'jquery': 
                $version = (array)Mage::getConfig()->getNode('jquery/versions/' . Mage::getStoreConfig('jquery/settings/jq_version'));
                $return  = $version['lib'];
                break;

            case 'noconflict':
                $return  = 'belvg/jquery/jquery.noconflict.js'; 
                break;

            default:
                break;
        }

        return $return;
    }
}