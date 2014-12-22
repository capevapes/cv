<?php
class TBT_RewardsReferral_Block_Adminhtml_Referrals_Import_Grid_Renderer_Status extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract 
{
	public function render(Varien_Object $importer)
	{	
		$value =  $importer->getData($this->getColumn()->getIndex());
		$helper = Mage::helper('rewardsref');
		switch ($value) {
			case TBT_Rewards_Model_Importer::STATUS_ENQUEUED:
				$status = '<span style="color:blue;">' . $helper->__('Scheduled') . '</span>';
				break;
				
			case TBT_Rewards_Model_Importer::STATUS_PROCESSING:
				if ($importer->getCountProcessed() == 0) {
					$status = '<span style="color:blue;">' . $helper->__('Processing') . '</span>';
					
				} else {
					if ($importer->getCountTotal() > 0) {						
						$progress = ($importer->getCountProcessed() / $importer->getCountTotal()) * 100;
						
					} else {
						$progress = '100';
					}
					$status = '
					<div style="border: 1px solid black;" title="'. intval($progress) . '% Complete">
						<div style="
							background-color: green;
							width: '.$progress.'%;
							color: white;
							text-align: center;
						">'
							. intval($progress) . '%' .
						'</div>
					</div>';
				}
				break;
				
			case TBT_Rewards_Model_Importer::STATUS_COMPLETE:
				$status = '<span style="color:green;">' . $helper->__('Complete') . '</span>';
				break;
				
			case TBT_Rewards_Model_Importer::STATUS_ERROR:
				$status = '<span style="color:red;">' . $helper->__('Failed') . '</span>';
				break;				
		}
		
		return $status;
	}	
}