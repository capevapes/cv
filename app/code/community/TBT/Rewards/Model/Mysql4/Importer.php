<?php

class TBT_Rewards_Model_Mysql4_Importer extends Mage_Core_Model_Mysql4_Abstract 
{
	protected function _construct()
	{
		$this->_init ( 'rewards/importer', 'importer_id' );
	}
}