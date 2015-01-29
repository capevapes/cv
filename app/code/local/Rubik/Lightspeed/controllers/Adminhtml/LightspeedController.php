<?php

class Rubik_Lightspeed_Adminhtml_LightspeedController extends Mage_Adminhtml_Controller_Action
{
    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('lightspeed/products')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('View Products'), Mage::helper('adminhtml')->__('View Products'));

        return $this;
    }

    public function indexAction()
    {
        $this->_initAction()
            ->renderLayout();
    }
}
