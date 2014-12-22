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
class Belvg_Jquery_Model_Source_Jqdisable
{
    /**
     * Getting array for select
     */
    public function toOptionArray()
    {    
        $tmp = Mage::getConfig()->getNode('jquery/compatibility');
        if (!empty($tmp)) {
            return array('1' => 'Enable');
        } else {
            return array('1' => 'Enable',
                         '0' => 'Disable');
        }
    }   
}