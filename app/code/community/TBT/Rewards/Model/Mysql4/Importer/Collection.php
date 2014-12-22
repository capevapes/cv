<?php

class TBT_Rewards_Model_Mysql4_Importer_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract 
{	
	protected function _construct()
	{
		$this->_init ( 'rewards/importer' );
		$this->setItemObjectClass('Varien_Object');		
		parent::_construct();
	}

	/**
	 * Once the collection is loaded with generic Varian_Objects, 
	 * we go back and instantiate the proper models for each importer class.
	 * The original items are removed and replaced with subclasses of the importer
	 * @see Mage_Core_Model_Resource_Db_Collection_Abstract::_afterLoad()
	 */
	protected function _afterLoad()
	{
		parent::_afterLoad();
		
		$newItems = array();						
		foreach ($this->getItems() as $object) {
			$type = $object->getType();
			$newModel = Mage::getModel($type);
			if (!is_object($newModel)) {
				Mage::logException(new Exception("Cannot find importer of type \"{$type}\""));
				continue;
			}
			
			$newModel->setData($object->getData());
			$newItems[] = $newModel;
		}
		
		$this->_items = array();
		foreach ($newItems as $item){
			$this->addItem($item);
		}
		
		return $this;
	}
	
	
}