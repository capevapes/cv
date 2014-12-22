<?php

class Infortis_Fortis_Adminhtml_ImportController extends Mage_Adminhtml_Controller_Action
{
	public function indexAction()
	{
		$this->getResponse()->setRedirect($this->getUrl("adminhtml/system_config/edit/section/fortis/"));
	}
	
	public function blocksAction()
	{
		$overwrite = Mage::helper('fortis')->getCfg('install/overwrite_blocks');
		Mage::getSingleton('fortis/import_cms')->importCmsItems('cms/block', 'blocks', $overwrite);
		
		$this->getResponse()->setRedirect($this->getUrl("adminhtml/system_config/edit/section/fortis/"));
	}
	
	public function pagesAction()
	{
		$overwrite = Mage::helper('fortis')->getCfg('install/overwrite_pages');
		Mage::getSingleton('fortis/import_cms')->importCmsItems('cms/page', 'pages', $overwrite);
		
		$this->getResponse()->setRedirect($this->getUrl("adminhtml/system_config/edit/section/fortis/"));
	}
}
