<?php
class TBT_RewardsReferral_Block_Adminhtml_Referrals_Import_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
  protected function _prepareForm()
  {
  		$helper = Mage::helper('rewardsref');
		$form = new Varien_Data_Form (
			array (
				'id' => 'edit_form',
				'action' => $this->getUrl('*/*/save', array(
					'id' => $this->getRequest()->getParam('id'))
				),
				'method' => 'post',
				'enctype' => 'multipart/form-data'
			)
		);
 
		$form->setUseContainer(true);
		$this->setForm($form);
      
		$fieldset = $form->addFieldset('import_file', array (
			'legend' =>	$helper->__('Import File')
		));
		
		$fieldset->addField('csvFile', 'file', array (
        	'label'     			=> $helper->__('CSV File'),
			'name'					=> "csvFile",				
			'required'  			=> true,
        	'value'  				=> 'Uplaod',
        	'after_element_html' 	=> '<br/><br/><small>See <a target="_blank" href="https://support.sweettoothrewards.com/entries/99404763-Importing-Referrals">documentation</a> for details</small>',
        	'tabindex' 				=> 1
        ));

		$fieldset = $form->addFieldset('import_options', array (
				'legend' =>	$helper->__('Import Options')
		));
		
		$fieldset->addField('override', 'checkbox', array (
			'label'     			=> $helper->__('Overwrite existing referrals'),
			'name'      			=> 'override',
			'checked' 				=> true,
			'onclick' 				=> "",
			'onchange' 				=> "",
			'value'  				=> '1',
			'disabled' 				=> false,
			'after_element_html' 	=> '&nbsp;<small>When enabled, an existing referral can be linked to a new referrer.</small>',
			'tabindex' 				=> 2
		));
		
		$fieldset->addField('only_existing_referrals', 'checkbox', array (
			'label'     			=> $helper->__('Referrals must have an account'),
			'name'      			=> 'only_existing_referrals',
			'checked' 				=> false,
			'onclick' 				=> "",
			'onchange' 				=> "",
			'value'  				=> '1',
			'disabled' 				=> false,
			'after_element_html' 	=> '&nbsp;
										<small>When enabled, only referrals with existing customer accounts can be imported.<br/>
										&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
										Otherwise, we\'ll link the two accounts once the referral signs up.</small>',
			'tabindex' 				=> 3
		));
		
		$fieldset = $form->addFieldset('import_results', array (
				'legend' =>	$helper->__('Import Results')
		));
        
		$adminUser = Mage::getSingleton('admin/session')->getUser();
		$email = $adminUser ?  $adminUser->getEmail() : "";
		
		$fieldset->addField('email', 'text', array(
				'label'     		 => $helper->__('Send results to:'),
				'class'     		 => 'required-entry validate-email',
				'required'  		 => true,
				'name'      		 => 'email',
				'value'  			 => $email,
				'disabled' 			 => false,
				'tabindex'			 => 4
		));
		
		return parent::_prepareForm();
  }
}