<?php
class TBT_RewardsReferral_Block_Adminhtml_Referrals_Import_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
	public function __construct()
	{
		parent::__construct();

		$this->_objectId = 'id';
		$this->_blockGroup = 'rewardsref';
		$this->_controller = 'adminhtml_referrals_import';
		 
		$this->_updateButton('save', 'label', Mage::helper('adminhtml')->__('Schedule Import'));		 
	}

	public function getHeaderText()
	{
		return Mage::helper('adminhtml')->__('New Referrals Import');
	}
}