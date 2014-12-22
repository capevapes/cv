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
class Belvg_Jquery_Block_Head extends Mage_Adminhtml_Block_Template
{
    protected $_libz   = array();
    protected $_jsurlz = array();
    
    public function addLib($libname)
    {
        $this->_libz[] = $libname;
        return $this;
    }
    
    public function addJs($jsurl)
    {
        $this->_jsurlz[] = $jsurl;
        return $this;
    }
    
    public function getLibz()
    {
        return $this->_libz;
    }
    
    public function getJsUrlz()
    {
        return $this->_jsurlz;
    }
}