<?php
class TBT_RewardsReferral_Adminhtml_Referrals_ImportController extends Mage_Adminhtml_Controller_Action
{
	public function indexAction()
	{
		$message = $this->_getHelper()->__('Imports happen in the background through the Magento CRON.');
		$message .= '&nbsp;<a href="https://support.sweettoothrewards.com/entries/21196536-Setting-up-CRON-Jobs-in-Magento">' . $this->_getHelper()->__('Learn More')  .'</a>';
		Mage::getSingleton('adminhtml/session')->addNotice($message);
		
		$this->loadLayout();
        $this->_addContent(
        	$this->getLayout()->createBlock('rewardsref/adminhtml_referrals_import')
		)->renderLayout();
	}
	
	public function gridAction()
	{
		$this->loadLayout();
		$this->getResponse()->setBody(
			$this->getLayout()->createBlock('rewardsref/adminhtml_referrals_import_grid')->toHtml()
		);
	}
	
	public function newAction()
	{
		$this->loadLayout();
		$this->_addContent($this->getLayout()->createBlock('rewardsref/adminhtml_referrals_import_edit'));
		$this->renderLayout();
	}
	
	public function saveAction()
	{
		try {
			$formData = $this->getRequest()->getPost();
			if (empty($formData) || empty($_FILES)){
				throw new Exception("Not enough data in your submission");
			}
			
			if (empty($formData['email'])){
				throw new Exception("We need an email address");
			}
			
			$importer = Mage::getModel('rewardsref/referral_importer');			
			
			// Prepare options
			$options['validate_referral_emails'] = true;
			$options['allow_non_existing_referrals'] = !isset($formData['only_existing_referrals']);
			$options['override'] = isset($formData['override']);
			
			
			// "csvFile" is the upload index in $_FILES
			Mage::getModel('rewardsref/referral_importer')->enqueue('csvFile', $formData['email'], $options);
			
			$message = "Your file has been scheduled for an import. <br/>
						The import will start within a few minutes depending on your CRON configuration. <br/>
						We'll email the results of the import to \"{$formData['email']}\" when done. No need to stay on this page.";
			
			Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('rewardsref')->__($message));
			$this->_redirect('*/*/index');
			
		} catch (Exception $e){
			Mage::getSingleton('adminhtml/session')->addError(Mage::helper('rewardsref')->__($e->getMessage()));
			$this->_redirect('*/*/new');				
		}
	}
}