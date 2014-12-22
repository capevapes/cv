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
class Belvg_Jquery_Model_Source_Jqversions
{
    /**
     * Getting array for select
     */
    public function toOptionArray()
    {    
        $result = array();
        foreach ((array)Mage::getConfig()->getNode('jquery/versions') as $key=>$item) {
            $tmp = (array)$item;
            $result[$key] = $tmp['label'];
        }

        foreach ((array)Mage::getConfig()->getNode('jquery/compatibility') as $key=>$item) {
            if (!empty($item)) {
                $result = array_intersect_key($result, (array)$item);         
            }
        }

        return $result;
    }
}