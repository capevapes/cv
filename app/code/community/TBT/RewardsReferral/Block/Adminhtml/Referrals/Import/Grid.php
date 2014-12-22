<?php

class TBT_RewardsReferral_Block_Adminhtml_Referrals_Import_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('referrals_import_grid');
        $this->setDefaultSort('importer_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }
    
    protected function _prepareCollection()
    {
    	$importers = TBT_Rewards_Model_Importer::getImportersCollection()
    		->addFieldToFilter('type', 'rewardsref/referral_importer');
    	$this->setCollection($importers);
    	parent::_prepareCollection();
    	return $this;
    }
    
    protected function _prepareColumns()
    {
    	$helper = Mage::helper('rewardsref');
    
    	$this->addColumn('importer_id', array(
    			'header' => $helper->__('Import ID'),
    			'index'  => 'importer_id',
    			'width'	 => '40px'
    	));

    	$this->addColumn('original_filename', array(
    			'header' => $helper->__('Uploaded File'),
    			'index'  => 'original_filename',
    			'width'	 => '150px'
    	));
    	 
    	$this->addColumn('created_at', array(
    			'header'       => $helper->__('Date Created'),
    			'index'        => 'created_at',
    			'type'		   => 'datetime',
    			'width'		   => '200px'
    			
    	));

    	$this->addColumn('count_total', array(
    			'header'       => $helper->__('Total Rows'),
    			'index'        => 'count_total',
    			'type'   	   => 'number',
    			'width'		   => '100px'
    	));
    	
    	$this->addColumn('status', array(
    			'header' => $helper->__('Import Status'),
    			'index'  => 'status',
    			'renderer'  => 'TBT_RewardsReferral_Block_Adminhtml_Referrals_Import_Grid_Renderer_Status',
    	));

    	    
    	$this->addColumn('started_at', array(
    			'header'       => $helper->__('Import Start'),
    			'index'        => 'started_at',
    			'type'		   => 'datetime'
    	));

    	$this->addColumn('ended_at', array(
    			'header'       => $helper->__('Import End'),
    			'index'        => 'ended_at',
    			'type'		   => 'datetime'
    	));
    	 
    	return parent::_prepareColumns();
    }
    
    public function getGridUrl()
    {
    	return $this->getUrl('*/*/grid', array('_current'=>true));
    }
}