<?php
//version 127

/** @noinspection PhpDocSignatureInspection */

/** @noinspection PhpDocSignatureInspection */

/** @noinspection PhpDocSignatureInspection */
class Microbiz_Connector_Model_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * for Magento Change records customers/product/categories bulk products 
     *
     * @return array of change records 
     * @author KT097
     */
    public function extendedgetSyncDetails()
    {
        $collection           = array();
        $batchsize            = Mage::helper('microbiz_connector')->getBatchSize();
        $headerdatacollection = Mage::getModel('extendedmbizconnector/extendedmbizconnector')->getCollection()->setOrder('header_id', 'asc')->addFieldToFilter('status',array('in' => array('Pending','Processing')))->setPageSize($batchsize)->getData();
        register_shutdown_function(array($this, 'mbizUpdateFatalErrorHandler'));
        foreach ($headerdatacollection as $headerdata) {

            $modelname                               = $headerdata['model_name'];
            $header_id                               = $headerdata['header_id'];
            Mage::unregister('sync_magento_status_header_id');
            Mage::register('sync_magento_status_header_id',$header_id);
            $collection[$header_id]['HeaderDetails'] = array(
                'model' => $modelname,
                'obj_id' => $headerdata['obj_id'],
                'mbiz_obj_id' => $headerdata['mbiz_obj_id'],
                'mbiz_ref_obj_id' => $headerdata['mbiz_ref_obj_id'],
                'ref_obj_id' => $headerdata['ref_obj_id'],
                'obj_status' => $headerdata['obj_status']
            );
            if ($headerdata['associated_configurable_products']) {
                $collection[$header_id]['HeaderDetails']['associated_configurable_products'] = unserialize($headerdata['associated_configurable_products']);
            }
			if($modelname == 'Orders') {
				$collection[$header_id]['ItemDetails'] = $this->getOrderinformation($headerdata['obj_id']);
			} 
			else {
				$itemdatacollection = Mage::getModel('syncitems/syncitems')->getCollection()->addFieldToFilter('header_id', $header_id)->getData();
				$modifieddata       = array();
				foreach ($itemdatacollection as $itemdata) {
					$attribute_name                = $itemdata['attribute_name'];
					$attribute_value               = (unserialize($itemdata['attribute_value'])) ? unserialize($itemdata['attribute_value']) : $itemdata['attribute_value'];
					$modifieddata[$attribute_name] = $attribute_value;
				}
				
				$collection[$header_id]['ItemDetails'] = $modifieddata;
			}
            $headerdata['status'] = 'Processing';
            Mage::getModel('extendedmbizconnector/extendedmbizconnector')->load($header_id)->setData($headerdata)->save();
        }
        $count=count($collection);
        $modifiedData=array();
        $modifiedData['recordsCount']=$count;
        $modifiedData['syncDetails']=$collection;

        return json_encode($modifiedData);
    }
    /**
     * for Magento Change records Update information customers/product/categories bulk products 
     * @param updateinfo updateinformation array from Mbiz
     * @return array of change records 
     * @author KT097
     */
    public function extendedmbizupdateApi($updatedinfo)
    {

        register_shutdown_function(array($this, 'mbizUpdateFatalErrorHandler'));
        $updatedinfo = json_decode($updatedinfo, true);
        foreach ($updatedinfo as $k => $updateitem) {
            $status = $updateitem['sync_status'];
            Mage::unregister('sync_magento_status_header_id');
            Mage::register('sync_magento_status_header_id',$k);
            $origData = Mage::getModel('extendedmbizconnector/extendedmbizconnector')->getCollection()->addFieldToFilter('header_id', $k)->getData();

            if ($status == 'Completed') {
               
					try {
						$mbiz_id                        = $updateitem['mbiz_obj_id'];
						$magento_id                     = $updateitem['obj_id'];
						$origData[0]['status']          = 'Completed';
						$origData[0]['mbiz_obj_id']     = $updateitem['mbiz_obj_id'];
						$origData[0]['mbiz_ref_obj_id'] = $updateitem['mbiz_ref_obj_id'];
						$modelname                      = $origData[0]['model_name'];
						// for saving the relation in relation tables 
						switch ($modelname) {
                            case 'Orders':
                                $updateitem['OrderDetails']['OrderHeaderDetails']['order_id'] = $magento_id;
                                $updateitem['OrderDetails']['OrderHeaderDetails']['mbiz_order_id'] = $mbiz_id;
                                $ordersData = $updateitem['OrderDetails'];
                                $this->updateOrderData($ordersData);

                                break;
                            case 'AttributeSets':
                                $checkObjectRelation = Mage::helper('microbiz_connector')->checkObjectRelation($magento_id, $modelname);

                                $relationinfo['magento_id']  = $magento_id;
                                $relationinfo['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
                                $relationinfo['mbiz_id']     = $mbiz_id;
                                if (!$checkObjectRelation && $origData[0]['obj_status'] != 2) {
                                    $model = Mage::getModel('mbizattributeset/mbizattributeset')->setData($relationinfo)->save();

                                }
Mage::Log($updateitem);
                                if(isset($updateitem['attribute_set_info']) && count($updateitem['attribute_set_info'])) {
                                    $attributeSetData['HeaderDetails']['mbiz_obj_id'] = $mbiz_id;
                                    $attributeSetData['HeaderDetails']['obj_id'] = $magento_id;
                                    $attributeSetData['HeaderDetails']['instanceId'] = Mage::helper('microbiz_connector')->getAppInstanceId();;
                                    $attributeSetData['ItemDetails'] = $updateitem['attribute_set_info'];
                                    $this->saveAttributeSetSync($attributeSetData);
                                }
                                break;
                            case 'Attributes':
                                $checkObjectRelation = Mage::helper('microbiz_connector')->checkObjectRelation($magento_id, $modelname);

                                $relationinfo['magento_id']  = $magento_id;
                                $relationinfo['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
                                $relationinfo['mbiz_id']     = $mbiz_id;
                                if (!$checkObjectRelation && $origData[0]['obj_status'] != 2) {
                                    $model = Mage::getModel('mbizattribute/mbizattribute')->setData($relationinfo)->save();

                                }
                                break;
							case 'Product':
								$checkObjectRelation = Mage::helper('microbiz_connector')->checkObjectRelation($magento_id, $modelname);
								
								$relationinfo['magento_id']  = $magento_id;
								$relationinfo['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
								$relationinfo['mbiz_id']     = $mbiz_id;

								if (!$checkObjectRelation && $origData[0]['obj_status'] != 2) {
									Mage::getModel('mbizproduct/mbizproduct')->setData($relationinfo)->save();
									
								}

								
								break;
							case 'Customer':
								$checkObjectRelation         = Mage::helper('microbiz_connector')->checkObjectRelation($magento_id, $modelname);
								$relationinfo['magento_id']  = $magento_id;
								$relationinfo['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
								$relationinfo['mbiz_id']     = $mbiz_id;
                                if($magento_id && $origData[0]['obj_status'] != 2)
                                {
                                    $customerModel = Mage::getModel("customer/customer")->load($magento_id);
                                    $customerModel->setSyncCusCreate(1);
                                    $customerModel->setSyncStatus(1);
                                    $customerModel->setPosCusStatus(1);
                                    $customerModel->save();
                                }
								if (!$checkObjectRelation && $origData[0]['obj_status'] != 2) {
									Mage::getModel('mbizcustomer/mbizcustomer')->setData($relationinfo)->save();
									
								}
								break;
							case 'CustomerAddressMaster':
								$checkObjectRelation = Mage::helper('microbiz_connector')->checkObjectRelation($magento_id, $modelname);
								
								$relationinfo['magento_id']  = $magento_id;
								$relationinfo['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
								$relationinfo['mbiz_id']     = $mbiz_id;
								if (!$checkObjectRelation && $origData[0]['obj_status'] != 2) {
									Mage::getModel('mbizcustomeraddr/mbizcustomeraddr')->setData($relationinfo)->save();
									
								}
								break;
                            case 'ProductCategories':
                                $checkObjectRelation = Mage::helper('microbiz_connector')->checkObjectRelation($magento_id, $modelname);
                                $relationinfo['magento_id']  = $magento_id;
                                $relationinfo['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
                                $relationinfo['mbiz_id']     = $mbiz_id;
                                $relationinfo['is_inventory_category'] = 0;

                                if(!$checkObjectRelation && $origData[0]['obj_status']!=2) {
                                    Mage::getModel('mbizcategory/mbizcategory')->setData($relationinfo)->save();
                                }
                                break;
						}
						$historyData = $origData[0];
						
						// moving success header data into history table header
						Mage::getModel('syncheaderhistory/syncheaderhistory')->setData($historyData)->save();
						
						$model1 = Mage::getModel('extendedmbizconnector/extendedmbizconnector')->load($k);
						// removing the success header data from header table
						try {
							$model1->delete();
						}
						catch (Mage_Core_Exception $e) {
							//$this->_fault('not_deleted', $e->getMessage());
							// Some errors while deleting.
						}
						$origitemsData = Mage::getModel('syncitems/syncitems')->getCollection()->addFieldToFilter('header_id', $k)->getData();
						foreach ($origitemsData as $origitemData) {
							$itemid = $origitemData['id'];
							unset($origitemData['id']);
							//moving the items information into history tables which is successfully updated in mbiz 
							Mage::getModel('syncitemhistory/syncitemhistory')->setData($origitemData)->save();
							$model1 = Mage::getModel('syncitems/syncitems')->load($itemid);
							// deleting the records form item table
							try {
								$model1->delete();
							}
							catch (Mage_Core_Exception $e) {
								//$this->_fault('not_deleted', $e->getMessage());
								// Some errors while deleting.
							}
						}
					}
					catch (Exception $e) {
                        Mage::Log($e->getMessage());
					}
				
            } else {
                $origData[0]['status']         = $status;
                $exception_desc                = $updateitem['exception_desc'];
                $origData[0]['exception_desc'] = $exception_desc;
                Mage::getModel('extendedmbizconnector/extendedmbizconnector')->load($origData[0]['header_id'])->setData($origData[0])->save();
            }
        }
        
        return "success";
    }
    /**
     * For creating/updating Mbiz records in Magento
     * @param $data
     * @param bool $debug
     * @internal param object $json containg multiple records of customers/products/categoris
     * @return status for each record in json format
     * @author KT097
     */
    public function extendedMbizApi($data,$debug = false)
    {
        register_shutdown_function(array($this, 'mbizFatalErrorHandler'));
        $locale = 'en_US';

        // changing locale works!

        Mage::app()->getLocale()->setLocaleCode($locale);
        Mage::app()->getTranslator()->init('frontend', true);
        Mage::app()->getTranslator()->init('adminhtml', true);
        // needed to add this
        Mage::app()->getTranslator()->setLocale($locale);
        $finalresult = array();
//return ($debug) ? $debug : $data;
        $data        = json_decode($data, true);
        ksort($data);

        foreach ($data as $k => $singledata) {
            $syncMbizStatusData = Mage::helper('microbiz_connector')->checkMbizSyncHeaderStatus($k);
            $modelname = $singledata['HeaderDetails']['model'];
            $result = array();
            ($debug) ? $result['recieved_microbiz_data'] = $singledata : null;
            Mage::unregister('sync_microbiz_status_header_id');
            Mage::register('sync_microbiz_status_header_id',$k);
            if(!$syncMbizStatusData) {

                Mage::helper('microbiz_connector')->createMbizSyncStatus($k,'Pending');
            switch ($modelname) {

                case 'Stores':
                    $inventoryData = $singledata['ItemDetails'];
                    $inventoryData['instance_id']=Mage::helper('microbiz_connector')->getAppInstanceId();
                    $storemodel = Mage::getModel('connector/storeinventorytotal_storeinventorytotal')->getCollection()->addFieldToFilter('company_id', $inventoryData['company_id'])->addFieldToFilter('store_id', $inventoryData['store_id'])->getData();
                    if (!count($storemodel)) {
                        $storesModel  = Mage::getModel('connector/storeinventorytotal_storeinventorytotal')->setData($inventoryData)->save();
                        $id = $storesModel->getId();
                    }
                    else {
                        $id = $storemodel[0]['id'];
                        Mage::getModel('connector/storeinventorytotal_storeinventorytotal')->load($id)->setData($inventoryData)->setId($id)->save();
                    }
                    if($id){
                        $result['instanceId']  = $singledata['HeaderDetails']['instanceId'];
                        $result['obj_id']      = $id;
                        $result['mbiz_obj_id'] = $singledata['HeaderDetails']['mbiz_obj_id'];
                        $result['sync_status'] = 'Completed';
                    }
                    else {
                        $result['instanceId']  = $singledata['HeaderDetails']['instanceId'];
                        $result['obj_id']      = '';
                        $result['mbiz_obj_id'] = $singledata['HeaderDetails']['mbiz_obj_id'];
                        $result['sync_status'] = 'Failed';
                        $result['exception_desc'] = "Exception while saving Store Information";
                    }
                    $finalresult[$k] = $result;
                    break;
                case 'AttributeSets':

                    if ($singledata['HeaderDetails']['obj_status'] == 2) {
                        $attributeSetId = $singledata['HeaderDetails']['obj_id'];
                        if (empty($attributeSetId)) {
                            $relationdata   = Mage::getModel('mbizattributeset/mbizattributeset')->getCollection()->addFieldToFilter('mbiz_id', $singledata['HeaderDetails']['mbiz_obj_id'])->setOrder('id', 'asc')->getData();
                            $attributeSetId = $relationdata[0]['magento_id'];
                        }
                        if ($attributeSetId) {
                            //Load product model collecttion filtered by attribute set id
                            $products = Mage::getModel('catalog/product')->getCollection()->addAttributeToSelect('*')->addFieldToFilter('attribute_set_id', $attributeSetId);

                            //process your product collection for removing product relation from relation table
                            foreach ($products as $p) {
                                $productinfo = $p->getData();
                                Mage::helper('microbiz_connector')->deleteAppRelation($productinfo['entity_id'], 'Product');
                            }
                            Mage::helper('microbiz_connector')->deleteAppRelation($attributeSetId, 'AttributeSets');
                            $result['instanceId']  = $singledata['HeaderDetails']['instanceId'];
                            $result['obj_id']      = $attributeSetId;
                            $result['mbiz_obj_id'] = $singledata['HeaderDetails']['mbiz_obj_id'];
                            $result['sync_status'] = 'Completed';
                        } else {
                            $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                            $result['obj_id']         = $attributeSetId;
                            $result['mbiz_obj_id']    = $singledata['HeaderDetails']['mbiz_obj_id'];
                            $result['sync_status']    = 'Failed';
                            $result['exception_desc'] = "Not exists";
                            $result['exception_id']   = 101;
                        }
                        $finalresult[$k] = $result;
                    } else {
                        try{
                            $finalresult[$k] = $this->saveAttributeSetSync($singledata);

                        }
                        catch(Mage_Api_Exception $ex) {
                            $finalresult[$k]['exception_full_desc'] = $ex->getCustomMessage();
                        }
                        $finalresult[$k] = $this->saveAttributeSetSync($singledata);
                    }
                    break;

                case 'Attributes':
                    if ($singledata['HeaderDetails']['obj_status'] == 2) {
                        $attributeId = $singledata['HeaderDetails']['obj_id'];
                        if (empty($attributeId)) {
                            $relationdata = Mage::getModel('mbizattribute/mbizattribute')->getCollection()->addFieldToFilter('mbiz_id', $singledata['HeaderDetails']['mbiz_obj_id'])->setOrder('id', 'asc')->getData();
                            $attributeId  = $relationdata[0]['magento_id'];
                        }
                        if ($attributeId) {
                            //Load product model collecttion filtered by attribute set id
                            Mage::helper('microbiz_connector')->deleteAppRelation($attributeId, 'Attributes');
                            $result['instanceId']  = $singledata['HeaderDetails']['instanceId'];
                            $result['obj_id']      = $attributeId;
                            $result['mbiz_obj_id'] = $singledata['HeaderDetails']['mbiz_obj_id'];
                            $result['sync_status'] = 'Completed';
                        } else {
                            $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                            $result['obj_id']         = $attributeId;
                            $result['mbiz_obj_id']    = $singledata['HeaderDetails']['mbiz_obj_id'];
                            $result['sync_status']    = 'Failed';
                            $result['exception_desc'] = "Not exists";
                            $result['exception_id']   = 101;
                        }
                        $finalresult[$k] = $result;
                    } else {
                        $attributeId          = '';
                        $attributeSetResponse = '';
                        $mbizAttributeId      = $singledata['HeaderDetails']['mbiz_obj_id'];
                        $attributeOptions = array();
                        if (isset($singledata['ItemDetails']['attribute_options'])) {
                            $attributeOptions = $singledata['ItemDetails']['attribute_options'];
                            unset($singledata['ItemDetails']['attribute_options']);
                        }
                        $exceptions = array();
                        if (empty($singledata['HeaderDetails']['obj_id'])) {
                            $attributeData          = $singledata['ItemDetails'];
                            $attributeCode          = $attributeData['attribute_code'];
                            $mbizAttributeCode      = $attributeCode;
                            $checkMbizAttributeCode = Mage::getModel('mbizattribute/mbizattribute')->getCollection()->addFieldToFilter('mbiz_attr_code', $attributeCode)->setOrder('id', 'asc')->getData();
                            if ($checkMbizAttributeCode) {
                                $attributeCode = $checkMbizAttributeCode[0]['magento_attr_code'];
                            }
                            $isAttributeExists = Mage::getModel('catalog/resource_eav_attribute')->loadByCode('catalog_product', $attributeCode);
                            if ($isAttributeExists->getId()) {

                                $attributeId     = $isAttributeExists->getId();
                                //Mage::getModel('Mage_Catalog_Model_Product_Attribute_Api')->update($isAttributeExists,$attribute);
                                $attributeUpdate = Mage::getModel('catalog/resource_eav_attribute')->load($attributeId);
                                // set frontend labels array with store_id as keys
                                try {

                                    $attributeUpdate->setAttributeId($attributeId);
                                    $attributeUpdate->setSourceModel($attributeUpdate->getSourceModel());
                                    $attributeUpdate->setIsGlobal($attributeUpdate->getIsGlobal());
                                    if(isset($attributeData['is_configurable'])) {
                                        $attributeUpdate->setIsConfigurable($attributeData['is_configurable']);
                                    }
                                    if(isset($attributeData['is_required'])) { $attributeUpdate->setIsRequired($attributeData['is_required']);
                                    }
                                    if(isset($attributeData['is_user_defined'])) {  $attributeUpdate->setIsUserDefined($attributeData['is_user_defined']); }
                                    if(isset($attributeData['is_used_for_promo_rules'])) {  $attributeUpdate->setIsUsedForPromoRules($attributeData['is_used_for_promo_rules']); }
                                    if(isset($attributeData['is_unique'])) {  $attributeUpdate->setIsUnique($attributeData['is_unique']); }
                                    if(isset($attributeData['frontend_label'])) {  $attributeUpdate->setFrontendLabel($attributeData['frontend_label']); }
                                    $attributeUpdate->save();
                                }
                                catch (Mage_Api_Exception $e) {
                                    $exceptions[] = $e->getCustomMessage();

                                }

                            }


                        } else {
                            try{
                                $attributeId   = $singledata['HeaderDetails']['obj_id'];
                                $attributeUpdate = Mage::getModel('catalog/resource_eav_attribute')->load($attributeId);

                                $singledata['ItemDetails']['is_configurable'] = (isset($singledata['ItemDetails']['is_configurable']) && !empty($singledata['ItemDetails']['is_configurable'])) ? $singledata['ItemDetails']['is_configurable'] : $attributeUpdate->getIsConfigurable();
                                $singledata['ItemDetails']['is_used_for_promo_rules'] = (isset($singledata['ItemDetails']['is_used_for_promo_rules']) && !empty($singledata['ItemDetails']['is_used_for_promo_rules'])) ? $singledata['ItemDetails']['is_used_for_promo_rules'] : $attributeUpdate->getIsUsedForPromoRules();
                                $singledata['ItemDetails']['is_required'] = (isset($singledata['ItemDetails']['is_required']) && !empty($singledata['ItemDetails']['is_required'])) ? $singledata['ItemDetails']['is_required'] : $attributeUpdate->getIsRequired();
                                $singledata['ItemDetails']['is_unique'] = (isset($singledata['ItemDetails']['is_unique']) && !empty($singledata['ItemDetails']['is_unique'])) ? $singledata['ItemDetails']['is_unique'] : $attributeUpdate->getIsUnique();
                                $singledata['ItemDetails']['source_model'] =  $attributeUpdate->getSourceModel();
                                //$singledata['ItemDetails']['scope'] =  $attributeUpdate->getScope();
                            $singledata['ItemDetails']['apply_to'] = (isset($singledata['ItemDetails']['apply_to'])) ? $singledata['ItemDetails']['apply_to']:$attributeUpdate->getApplyTo();
                                switch($attributeUpdate->getIsGlobal()) {
                                    case 0: $singledata['ItemDetails']['scope'] = 'store';
                                        break;
                                    case 1: $singledata['ItemDetails']['scope'] = 'global';
                                        break;
                                    case 2: $singledata['ItemDetails']['scope'] = 'website';
                                        break;
                                    default: $singledata['ItemDetails']['scope'] = 'global';
                                    break;
                                }
                                $attributeData = $singledata['ItemDetails'];
                                Mage::getModel('Mage_Catalog_Model_Product_Attribute_Api')->update($attributeId, $attributeData);
                            }
                            catch (Mage_Api_Exception $e) {
                                $exceptions[] = $e->getCustomMessage();

                            }

                        }
                        if ($attributeId) {
                            try{
                                $attributeUpdate = Mage::getSingleton('eav/config')->getAttribute('catalog_product', $attributeId);
                                if (($attributeUpdate->getSourceModel() == 'eav/entity_attribute_source_table' ||  is_null($attributeUpdate->getSourceModel()))) {
                                    Mage::Log($attributeOptions);
                                    $attributeCode = $attributeUpdate->getAttributeCode();
                                    $arrtrResponse =  $this->updateAttributeOptions($attributeCode, $attributeOptions,$mbizAttributeId);
                                }
                                $attributeRelation['instance_id']       = Mage::helper('microbiz_connector')->getAppInstanceId();
                                $attributeRelation['magento_id']        = $attributeId;
                                $attributeRelation['mbiz_id']           = $mbizAttributeId;
                                $attributeRelation['magento_attr_code'] = $attributeCode;
                                $attributeRelation['mbiz_attr_code']    = $mbizAttributeCode;
                                $attributeRelation['mbiz_attr_set_id']  = '';
                                $checkAttributeRelation                 = Mage::getModel('mbizattribute/mbizattribute')->getCollection()->addFieldToFilter('magento_id', $attributeId)->setOrder('id', 'asc')->getData();

                                if (!$checkAttributeRelation) {
                                    $model = Mage::getModel('mbizattribute/mbizattribute')->setData($attributeRelation)->save();
                                }
                                $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                                $result['obj_id']         = $attributeId;
                                $result['mbiz_obj_id']    = $singledata['HeaderDetails']['mbiz_obj_id'];
                                $result['exception_desc'] = 'Attribute Option value not defined';
                                $result['sync_status']    = (count($arrtrResponse)) ? 'Completed' : 'Failed';
                                (isset($arrtrResponse['attribute_info'])) ? $result['attribute_info'] = $arrtrResponse['attribute_info'] : null;

                            }
                            catch (Mage_Api_Exception $e) {
                                $exceptions[] = $e->getCustomMessage();
                                $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                                $result['obj_id']         = $attributeId;
                                $result['mbiz_obj_id']    = $singledata['HeaderDetails']['mbiz_obj_id'];
                                $result['sync_status']    = 'Failed';
                                $result['exception_desc'] = implode("\n",$exceptions);
                                $result['exception_id']   = '';
                            }

                        } else {
                            $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                            $result['obj_id']         = $attributeId;
                            $result['mbiz_obj_id']    = $singledata['HeaderDetails']['mbiz_obj_id'];
                            $result['sync_status']    = 'Failed';
                            $result['exception_desc'] = implode("\n",$exceptions);
                            $result['exception_id']   = '';
                        }
                        $finalresult[$k] = $result;
                    }
                    break;
                case 'Product':
                    if ($singledata['HeaderDetails']['obj_status'] == 2) {
                        $productId = $singledata['HeaderDetails']['obj_id'];
                        if (empty($productId)) {
                            $relationdata = Mage::getModel('mbizproduct/mbizproduct')->getCollection()->addFieldToFilter('mbiz_id', $singledata['HeaderDetails']['mbiz_obj_id'])->setOrder('id', 'asc')->getData();
                            $productId    = $relationdata[0]['magento_id'];
                        }
                        if ($productId) {
                            Mage::helper('microbiz_connector')->deleteAppRelation($productId, 'Product');
                            $result['instanceId']  = $singledata['HeaderDetails']['instanceId'];
                            $result['obj_id']      = $productId;
                            $result['mbiz_obj_id'] = $singledata['HeaderDetails']['mbiz_obj_id'];
                            $result['sync_status'] = 'Completed';
                        } else {
                            $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                            $result['obj_id']         = $productId;
                            $result['mbiz_obj_id']    = $singledata['HeaderDetails']['mbiz_obj_id'];
                            $result['sync_status']    = 'Failed';
                            $result['exception_desc'] = "Not exists";
                            $result['exception_id']   = 101;
                        }
						$connectorDebug = array();
                        $connectorDebug['instance_id'] = $result['instanceId'];
                        $connectorDebug['status'] = $result['sync_status'];
                        $connectorDebug['status_msg'] = "Product with ".$productId."  ".$result['exception_desc'];
                        Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();
                        $finalresult[$k] = $result;
                        
                    } else {
                        $productid = '';
                        if (empty($singledata['HeaderDetails']['obj_id'])) {
                            $sku = $singledata['ItemDetails']['sku'];
                            $productid = '';
                            $typeid = $singledata['ItemDetails']['product_type_id'];
                            switch ($typeid) {
                                case "1":
                                    $type = "simple";
                                    break;
                                case "2":
                                    $type = "configurable";
                                    break;
                            }
                            $set         = $singledata['HeaderDetails']['ref_obj_id'];
                            if(!$set) {
                                $set = Mage::helper('microbiz_connector')->getObjectRelation($singledata['HeaderDetails']['mbiz_ref_obj_id'], 'AttributeSets','Mbiz');
                            }
                            $mbiz_id     = $singledata['HeaderDetails']['mbiz_obj_id'];
                            $productData = $singledata['ItemDetails'];
                            $storeInventory  = $productData['store_inventory'];
                            unset($productData['store_inventory']);
                            unset($productData['attribute_set_id']);
                            unset($productData['product_id']);
                            try {
                                $productexists = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);

                                if ($productexists) {
                                    $productexistsdata = $productexists->getData();
                                    unset($productData['tax_class_id']);
                                    if($type == $productexists->getTypeId() && $productexists->getAttributeSetId() == $set) {
                                        $productid         = $productexistsdata['entity_id'];
                                        $productupdated    = Mage::getModel('Microbiz_Connector_Model_Product_Api')->update($productid, $productData, $store = null, $identifierType = null);

                                    }
                                    else {
                                       $result['instanceId']  = $singledata['HeaderDetails']['instanceId'];
                                        $result['obj_id']      = '';
                                        $result['mbiz_obj_id'] = $mbiz_id;
                                        $result['sync_status'] = 'Failed';
                                        $result['exception_desc'] = 'Type or AttributeSet not matching with Existing product';
                                        $result['exception_full_desc'] = 'Product Already Exists with Same Sku but Product Type or AttributeSet is not maatching with The Magento product';
                                        $result['exception_id']   = '';

                                    }
                                } else {
									
                                    $productData['websites'] = array(
                                        Mage::getStoreConfig('connector/defaultwebsite/product')
                                    );
                                    $productid               = Mage::getModel('Microbiz_Connector_Model_Product_Api')->create($type, $set, $sku, $productData);

                                }
                                if($productid) {
                                    ($storeInventory) ? Mage::getModel('Microbiz_Connector_Model_Storeinventory_Api')->createMbizInventory($storeInventory, $productid) : null;
                                $relationinfo['magento_id']  = $productid;
                                $relationinfo['mbiz_id']     = $mbiz_id;
                                $relationinfo['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
                                $checkObjectRelation         = Mage::helper('microbiz_connector')->checkObjectRelation($productid, $modelname);
                                
                                if (!$checkObjectRelation) {
                                    Mage::getModel('mbizproduct/mbizproduct')->setData($relationinfo)->save();
                                }
                                if (isset($singledata['HeaderDetails']['associated_configurable_products'])) {
                                    $associated_configurable_products = $singledata['HeaderDetails']['associated_configurable_products'];
                                    $configMsg=Mage::helper('microbiz_connector')->saveSimpleConfig($productid, $associated_configurable_products);

                                }
                                $result['instanceId']  = $singledata['HeaderDetails']['instanceId'];
                                $result['obj_id']      = $productid;
                                $result['mbiz_obj_id'] = $mbiz_id;
                                $result['sync_status'] = 'Completed';

                                if(is_array($configMsg)) {
                                    $result['sync_status'] = 'Failed';
                                    $result['exception_desc'] = 'Product Created but unable to save configurable product Associations. '.$configMsg['exception_desc'];
                                    $result['exception_id']   = '';
                                }
                            }
                                
                            }
                            catch (Mage_Api_Exception $ex) {
                                $result['sync_status']    = 'Failed';
                                $result['exception_desc'] = $ex->getMessage();
                                $result['exception_full_desc'] = $ex->getCustomMessage();
                                $result['exception_id']   = $ex->getCode();
                            }
                            $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                            $result['mbiz_obj_id']    = $mbiz_id;


							$connectorDebug = array();
                            $connectorDebug['instance_id'] = $result['instanceId'];
                            $connectorDebug['status'] = $result['sync_status'];
                            $connectorDebug['status_msg'] = "Product ".$productid."  ".$result['exception_full_desc'];
                            Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();
                            $finalresult[$k] = $result;
                        } else {
                            $productId   = $singledata['HeaderDetails']['obj_id'];
                            $productData = $singledata['ItemDetails'];
                            $mbiz_id     = $singledata['HeaderDetails']['mbiz_obj_id'];
                            unset($productData['attribute_set_id']);
                            unset($productData['product_id']);
                            try {
                                //unset($productData['tax_class_id']);
                                $skuFlag = false;
                               if(isset($productData['sku']) && $productData['sku']) {
                                   $sku = $productData['sku'];
                                   $idBySku = Mage::getModel('catalog/product')->getIdBySku($sku);
                                   $skuFlag = ($idBySku != $productId) ? true : false ;
                               }



                                if($skuFlag) {
                                    $result['sync_status']    = 'Failed';
                                    $result['exception_desc'] = 'Sku must be unique';
                                    $result['exception_full_desc'] = 'Product already exists(Product Id:'.$idBySku.') with this SKU';
                                    $result['exception_id']   = '';
                                } else {
                                    $productupdated = Mage::getModel('Microbiz_Connector_Model_Product_Api')->update($productId, $productData, $store = null, $identifierType = null);
                                    $relationinfo['magento_id']  = $productId;
                                    $relationinfo['mbiz_id']     = $mbiz_id;
                                    $relationinfo['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
                                    $checkObjectRelation         = Mage::helper('microbiz_connector')->checkObjectRelation($productId, $modelname);

                                    if (!$checkObjectRelation) {
                                        Mage::getModel('mbizproduct/mbizproduct')->setData($relationinfo)->save();

                                    }
                                    if (isset($singledata['HeaderDetails']['associated_configurable_products'])) {

                                        $associated_configurable_products = $singledata['HeaderDetails']['associated_configurable_products'];
                                        $configMsg=Mage::helper('microbiz_connector')->saveSimpleConfig($productId, $associated_configurable_products);

                                    }
                                    $result['instanceId']  = $singledata['HeaderDetails']['instanceId'];
                                    $result['obj_id']      = $productId;
                                    $result['mbiz_obj_id'] = $mbiz_id;
                                    $result['sync_status'] = 'Completed';

                                    if(is_array($configMsg)) {
                                        $result['sync_status'] = 'Failed';
                                        $result['exception_desc'] = 'Product Created but unable to save configurable product Associations. '.$configMsg['exception_desc'];
                                        $result['exception_id']   = '';
                                    }
                                }

                            }
                            catch (Mage_Api_Exception $ex) {
                                $result['sync_status']    = 'Failed';
                                $result['exception_desc'] = $ex->getMessage();
                                $result['exception_full_desc'] = $ex->getCustomMessage();
                                $result['exception_id']   = $ex->getCode();
                            }
                            $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                            $result['obj_id']         = $productId;
                            $result['mbiz_obj_id']    = $mbiz_id;


							$connectorDebug = array();
                            $connectorDebug['instance_id'] = $result['instanceId'];
                            $connectorDebug['status'] = $result['sync_status'];
                            $connectorDebug['status_msg'] = "Product ".$productId."  ".$result['exception_full_desc'];
                            Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();
                            $finalresult[$k] = $result;
                        }
                    }
                    break;
                case 'Customer':
					$customerid = '';
                    if ($singledata['HeaderDetails']['obj_status'] == 2) {
                        $customerId = $singledata['HeaderDetails']['obj_id'];
                        if (empty($customerId)) {
                            $relationdata = Mage::getModel('mbizcustomer/mbizcustomer')->getCollection()->addFieldToFilter('mbiz_id', $singledata['HeaderDetails']['mbiz_obj_id'])->setOrder('id', 'asc')->getData();
                            $customerId   = $relationdata[0]['magento_id'];
                        }
                        if ($customerId) {
                            Mage::helper('microbiz_connector')->deleteAppRelation($customerId, 'Customer');
                            $re_customer  = Mage::getModel('customer/customer')->load($customerId);
                            $addressarray = array();
                            foreach ($re_customer->getAddresses() as $address) {
                                $data           = $address->toArray();
                                $addressarray[] = $data['entity_id'];
                            }
                            foreach ($addressarray as $addressid) {
                                Mage::helper('microbiz_connector')->deleteAppRelation($addressid, 'CustomerAddressMaster');
                            }
                            $result['instanceId']  = $singledata['HeaderDetails']['instanceId'];
                            $result['obj_id']      = $customerId;
                            $result['mbiz_obj_id'] = $singledata['HeaderDetails']['mbiz_obj_id'];
                            $result['sync_status'] = 'Completed';
                        } else {
                            $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                            $result['obj_id']         = $customerId;
                            $result['mbiz_obj_id']    = $singledata['HeaderDetails']['mbiz_obj_id'];
                            $result['sync_status']    = 'Failed';
                            $result['exception_desc'] = "Not exists";
                            $result['exception_id']   = 101;
                        }
						$connectorDebug = array();
                        $connectorDebug['instance_id'] = $result['instanceId'];
                        $connectorDebug['status'] = $result['sync_status'];
                        $connectorDebug['status_msg'] = "Customer ".$customerId."  ".$result['exception_desc'];
                        Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();
                        $finalresult[$k] = $result;
                    } else {
                        if (empty($singledata['HeaderDetails']['obj_id'])) {
                            $customerid = '';
                            $mbiz_id                  = $singledata['HeaderDetails']['mbiz_obj_id'];
                            $relationmbizcustomerdata = Mage::getModel('mbizcustomer/mbizcustomer')->getCollection()->addFieldToFilter('mbiz_id', $mbiz_id)->setOrder('id', 'asc')->getData();
                            if (count($relationmbizcustomerdata)) {
                                $customerid = $relationmbizcustomerdata[0]['magento_id'];
                            }
                            $customerinfo = $singledata['ItemDetails'];
                            
                            try {
                                if (!$customerid) {
                                    $customer_email = $customerinfo['email'];
                                    $collection     = Mage::getModel('customer/customer')->getCollection()->addAttributeToSelect('*')->addAttributeToFilter('email', $customer_email);
                                    foreach ($collection as $customer) {
                                        $customerarray = $customer->toArray();
                                    }
                                    $customerid = $customerarray['entity_id'];
                                }
                                if ($customerid) {
                                    Mage::getModel('Mage_Customer_Model_Customer_Api')->update($customerid, $customerinfo);
                                } else {
                                    $singledata['ItemDetails']['created_at']      = date("Y-m-d H:m:s");
                                    $singledata['ItemDetails']['website_id']      = Mage::getStoreConfig('connector/defaultwebsite/customer');
                                    $singledata['ItemDetails']['sync_cus_create'] = 1;
                                    $singledata['ItemDetails']['sync_status']     = 1;
                                    $customerid                                   = Mage::getModel('Microbiz_Connector_Model_Customer_Api')->create($singledata['ItemDetails']);
                                }
                                $relationinfo['magento_id']  = $customerid;
                                $relationinfo['mbiz_id']     = $mbiz_id;
                                $result['obj_id']      = $customerid;

                                $relationinfo['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
                                $checkObjectRelation         = Mage::helper('microbiz_connector')->checkObjectRelation($customerid, $modelname);
                                
                                if (!$checkObjectRelation) {
                                    Mage::getModel('mbizcustomer/mbizcustomer')->setData($relationinfo)->save();
                                    
                                }
                                $result['sync_status'] = 'Completed';
                            }
                            catch (Mage_Api_Exception $ex) {
                                $result['exception_desc'] = $ex->getMessage();
                                $result['exception_full_desc'] = $ex->getCustomMessage();
                                $result['exception_id']   = $ex->getCode();
                                $result['sync_status']    = 'Failed';
                            }
                            $result['mbiz_obj_id']    = $mbiz_id;
                            $result['instanceId']  = $singledata['HeaderDetails']['instanceId'];

							$connectorDebug = array();
                            $connectorDebug['instance_id'] = $result['instanceId'];
                            $connectorDebug['status'] = $result['sync_status'];
                            $connectorDebug['status_msg'] = "Customer with Mbiz id".$mbiz_id."  ".$result['exception_desc'];
                            Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();
                            $finalresult[$k] = $result;
                        } else {
                            $customerId   = $singledata['HeaderDetails']['obj_id'];
                            $customerinfo = $singledata['ItemDetails'];
                            $mbiz_id      = $singledata['HeaderDetails']['mbiz_obj_id'];
                            try {
                                $customerid                  = Mage::getModel('Mage_Customer_Model_Customer_Api')->update($customerId, $customerinfo);
                                $relationinfo['magento_id']  = $customerId;
                                $relationinfo['mbiz_id']     = $mbiz_id;
                                $relationinfo['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
                                $checkObjectRelation         = Mage::helper('microbiz_connector')->checkObjectRelation($customerId, $modelname);
                                $result['sync_status'] = 'Completed';

                                if (!$checkObjectRelation) {
                                    Mage::getModel('mbizcustomer/mbizcustomer')->setData($relationinfo)->save();
                                    
                                }
                            }
                            catch (Mage_Api_Exception $ex) {
                                $result['sync_status']    = 'Failed';
                                $result['exception_full_desc'] = $ex->getCustomMessage();
                                $result['exception_desc'] = $ex->getMessage();
                                $result['exception_id']   = $ex->getCode();
                            }

                            $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                            $result['obj_id']         = $customerId;
                            $result['mbiz_obj_id']    = $mbiz_id;
							$connectorDebug = array();
                            $connectorDebug['instance_id'] = $result['instanceId'];
                            $connectorDebug['status'] = $result['sync_status'];
                            $connectorDebug['status_msg'] = "Customer with Mbiz id".$mbiz_id."  ".$result['exception_desc'];
                            Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();
                            $finalresult[$k] = $result;
                        }
                    }
                    break;
                case 'CustomerAddressMaster':
					$customeraddressidid = '';

                    if ($singledata['HeaderDetails']['obj_status'] == 2) {
                        $customeraddressId = $singledata['HeaderDetails']['obj_id'];
                        if (empty($customeraddressId)) {
                            $relationdata      = Mage::getModel('mbizcustomeraddr/mbizcustomeraddr')->getCollection()->addFieldToFilter('mbiz_id', $singledata['HeaderDetails']['mbiz_obj_id'])->setOrder('id', 'asc')->getData();
                            $customeraddressId = $relationdata[0]['magento_id'];
                        }
                        if ($customeraddressId) {
                            Mage::helper('microbiz_connector')->deleteAppRelation($customeraddressId, 'CustomerAddressMaster');
                            $result['instanceId']  = $singledata['HeaderDetails']['instanceId'];
                            $result['obj_id']      = $customeraddressId;
                            $result['mbiz_obj_id'] = $singledata['HeaderDetails']['mbiz_obj_id'];
                            $result['sync_status'] = 'Completed';
                        } else {
                            $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                            $result['obj_id']         = $customeraddressId;
                            $result['mbiz_obj_id']    = $singledata['HeaderDetails']['mbiz_obj_id'];
                            $result['sync_status']    = 'Failed';
                            $result['exception_desc'] = "Customer Not exists";
                            $result['exception_id']   = 101;
                        }
						$connectorDebug = array();
                        $connectorDebug['instance_id'] = $result['instanceId'];
                        $connectorDebug['status'] = $result['sync_status'];
                        $connectorDebug['status_msg'] = "Customer with ".$customeraddressId."  ".$result['exception_desc'];
                        Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();
                        $finalresult[$k] = $result;
                    } else {
                        if (empty($singledata['HeaderDetails']['obj_id'])) {
                            $customerId      = $singledata['HeaderDetails']['ref_obj_id'];
                            $mbiz_ref_obj_id = $singledata['HeaderDetails']['mbiz_ref_obj_id'];
                            if (!$customerId) {
                                $relationcustomerdata = Mage::getModel('mbizcustomer/mbizcustomer')->getCollection()->addFieldToFilter('mbiz_id', $mbiz_ref_obj_id)->setOrder('id', 'asc')->getData();
                                $customerId           = $relationcustomerdata[0]['magento_id'];
                            }
                            $customerAddressData = $singledata['ItemDetails'];
                            $region              = Mage::getModel('directory/region')->getCollection()->addFieldToFilter('country_id', $customerAddressData['country'])->addFieldToFilter('code', $customerAddressData['state'])->getData();
                            if (count($region)) {
                                $customerAddressData['region_id'] = $region[0]['region_id'];
                            } else {
                                $customerAddressData['region'] = $customerAddressData['state'];
                            }
                            $mbiz_id                                    = $singledata['HeaderDetails']['mbiz_obj_id'];
                            $customerAddressData['street']              = array(
                                $customerAddressData['address'],
                                $customerAddressData['address2']
                            );
                            $customerAddressData['country_id']          = $customerAddressData['country'];
                            $customerAddressData['company']             = $customerAddressData['company_name'];
                            $customerAddressData['postcode']            = $customerAddressData['zipcode'];
                            $customerAddressData['telephone']           = $customerAddressData['phone'];
                            try {
                                
                                $customeraddressidid = Mage::getModel('Mage_Customer_Model_Address_Api')->create($customerId, $customerAddressData);

                                
                                $relationinfo['magento_id']  = $customeraddressidid;
                                $relationinfo['mbiz_id']     = $mbiz_id;
                                $relationinfo['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
                                $checkObjectRelation         = Mage::helper('microbiz_connector')->checkObjectRelation($customeraddressidid, $modelname);
                                
                                if (!$checkObjectRelation) {
                                    $model = Mage::getModel('mbizcustomeraddr/mbizcustomeraddr')->setData($relationinfo)->save();
                                    
                                }
                                $result['obj_id']          = $customeraddressidid;
                                $result['sync_status']     = 'Completed';
                            }
                            catch (Mage_Api_Exception $ex) {
                                $result['sync_status']     = 'Failed';
                                $result['exception_full_desc'] = $ex->getCustomMessage();
                                $result['exception_desc']  = $ex->getMessage();
                                $result['exception_id']    = $ex->getCode();
                            }
                            $result['instanceId']      = $singledata['HeaderDetails']['instanceId'];

                            $result['mbiz_obj_id']     = $mbiz_id;
                            $result['ref_obj_id']      = $customerId;
                            $result['mbiz_ref_obj_id'] = $mbiz_ref_obj_id;

							$connectorDebug = array();
                            $connectorDebug['instance_id'] = $result['instanceId'];
                            $connectorDebug['status'] = $result['sync_status'];
                            $connectorDebug['status_msg'] = "Customer with Mbiz Id ".$mbiz_ref_obj_id." and Address Id ".$mbiz_id."  ".$result['exception_desc'];
                            Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();
                            $finalresult[$k] = $result;
                        } else {
                            $mbiz_id                   = $singledata['HeaderDetails']['mbiz_obj_id'];
                            $mbiz_ref_obj_id           = $singledata['HeaderDetails']['mbiz_ref_obj_id'];
                            $addressId                 = $singledata['HeaderDetails']['obj_id'];
                            $addressData               = $singledata['ItemDetails'];
                            $addressData['street']     = array(
                                $addressData['address'],
                                $addressData['address2']
                            );
                            $addressData['country_id'] = $addressData['country'];
                            
                            $addressData['postcode']            = $addressData['zipcode'];
                            $addressData['telephone']           = $addressData['phone'];
                            $region                             = Mage::getModel('directory/region')->getCollection()->addFieldToFilter('country_id', $addressData['country'])->addFieldToFilter('code', $addressData['state'])->getData();
                            if (count($region)) {
                                $addressData['region_id'] = $region[0]['region_id'];
                            } else {
                                $addressData['region'] = $addressData['state'];
                            }
                            try {
                                $customeraddressidid         = Mage::getModel('Mage_Customer_Model_Address_Api')->update($addressId, $addressData);
                                $relationinfo['magento_id']  = $addressId;
                                $relationinfo['mbiz_id']     = $mbiz_id;
                                $relationinfo['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
                                $checkObjectRelation         = Mage::helper('microbiz_connector')->checkObjectRelation($addressId, $modelname);
                                if (!$checkObjectRelation) {
                                    Mage::getModel('mbizcustomeraddr/mbizcustomeraddr')->setData($relationinfo)->save();
                                    
                                }
                                $result['sync_status']     = 'Completed';

                            }
                            catch (Mage_Api_Exception $ex) {
                                $result['sync_status']     = 'Failed';
                                $result['exception_full_desc'] = $ex->getCustomMessage();
                                $result['exception_desc']  = $ex->getMessage();
                                $result['exception_id']    = $ex->getCode();
                            }
                            $result['instanceId']      = $singledata['HeaderDetails']['instanceId'];
                            $result['obj_id']          = $addressId;
                            $result['mbiz_obj_id']     = $mbiz_id;
                            $result['mbiz_ref_obj_id'] = $mbiz_ref_obj_id;

							$connectorDebug = array();
                            $connectorDebug['instance_id'] = $result['instanceId'];
                            $connectorDebug['status'] = $result['sync_status'];
                            $connectorDebug['status_msg'] = "Customer with Mbiz Id ".$mbiz_ref_obj_id." and Address Id ".$mbiz_id."  ".$result['exception_desc'];
                            Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();
                            $finalresult[$k] = $result;
                        }
                    }

                    break;
                case 'ProductCategories':

                    if($singledata['HeaderDetails']['obj_status']==1)
                    {
                    if (empty($singledata['HeaderDetails']['obj_id'])) {
                        $mbizCattId     = $singledata['HeaderDetails']['mbiz_obj_id'];
                        $categoryData = $singledata['ItemDetails'];
                        $categoryRelationModel = Mage::getModel('mbizcategory/mbizcategory')
                            ->getCollection()
                            ->addFieldToFilter('mbiz_id', $mbizCattId)
                            ->setOrder('id','asc')
                            ->getData();

                            if(count($categoryRelationModel)>0)
                            {
                                $categoryId = $categoryRelationModel[0]['magento_id'];
                                try {
                                    $category = Mage::getModel('catalog/category')->load($categoryId);

                                    $MbizParentId = $categoryData['parent_id'];
                                    $magCurrentParentId = $category->getParentId();
                                    Mage::log($MbizParentId,null,'syncproduct.log');
                                    $categoryParentRelationModel = Mage::getModel('mbizcategory/mbizcategory')
                                        ->getCollection()
                                        ->addFieldToFilter('mbiz_id', $MbizParentId)
                                        ->setOrder('id','asc')
                                        ->getFirstItem()->getData();
                                    Mage::log("category parent rel",null,'syncproduct.log');
                                    Mage::log($categoryParentRelationModel,null,'syncproduct.log');
                                    if(!empty($categoryParentRelationModel))
                                    {
                                        $magParentId = $categoryParentRelationModel['magento_id'];
                                        if($magCurrentParentId!=$magParentId)
                                        {
                                            Mage::log("category is moved...",null,'syncproduct.log');
                                            $categoryData['parent_id'] = $magParentId;
                                            $category = Mage::getModel('catalog/category')->load($categoryId);
                                            $category->move($magParentId, null);
                                        }
                                        else {
                                            Mage::log("category is updated",null,'syncproduct.log');
                                            $categoryData['parent_id'] = $magCurrentParentId;
                                        }

                                    }
                                    else {
                                        Mage::log("category parent rel not exits",null,'syncproduct.log');
                                        $categoryData['parent_id'] = $magCurrentParentId;
                                    }

                                    $categoryRelExists = Mage::getModel('mbizcategory/mbizcategory')
                                        ->getCollection()
                                        ->addFieldToFilter('magento_id', $categoryId)
                                        ->setOrder('id','asc')
                                        ->getFirstItem()->getData();
                                    if($singledata['ItemDetails']['parent_id']==1 && !empty($categoryRelExists) && $magCurrentParentId!=$MbizParentId)
                                    {
                                        Mage::log("sub category changed to root in mbiz ",null,'syncproduct.log');
                                        $categoryData['sync_cat_create']='1';
                                        $category = Mage::getModel('catalog/category')->load($categoryId);
                                        $category->move($MbizParentId, null);
                                    }
                                    if($singledata['ItemDetails']['parent_id']==1)
                                    {
                                        Mage::log("Root category Imported from mbiz",null,'syncproduct.log');
                                        $categoryData['sync_cat_create']='1';
                                        $categoryData['parent_id']='1';
                                    }

                                    $OrigCatData = $category->getData();
                                    $updatedData = array_merge($OrigCatData,$categoryData);

                                    $postDataConfig = array();
                                    if(!array_key_exists('default_sort_by',$updatedData))
                                    {

                                        $updatedData['default_sort_by'] = 'position';
                                    }
                                    if(empty($updatedData['available_sort_by']))
                                    {

                                        $updatedData['available_sort_by'] = 'position';
                                    }
                                    if(!empty($postDataConfig))
                                    {
                                        $updatedData['use_config'] = $postDataConfig;
                                    }

                                    $categoryid = Mage::getModel('Mage_Catalog_Model_Category_Api')->update($categoryId, $updatedData);
                                    $mbizCattId     = $singledata['HeaderDetails']['mbiz_obj_id'];
                                    $result['instanceId']  = $singledata['HeaderDetails']['instanceId'];
                                    $result['obj_id'] = $categoryId;
                                    $result['mbiz_obj_id']      = $mbizCattId;
                                    $result['sync_status'] = 'Completed';
                                }
                                catch (Mage_Api_Exception $ex) {
                                    $mbizCattId     = $singledata['HeaderDetails']['mbiz_obj_id'];
                                    $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                                    $categoryid               = $categoryId;
                                    $result['obj_id']         = $categoryId;
                                    $result['mbiz_obj_id']      = $mbizCattId;
                                    $result['sync_status']    = 'Failed';
                                    $result['exception_full_desc'] = $ex->getCustomMessage();
                                    $result['exception_desc'] = $ex->getMessage();
                                    $result['exception_id']   = '';
                                }

                                $connectorDebug = array();
                                $connectorDebug['instance_id'] = $result['instanceId'];
                                $connectorDebug['status'] = $result['sync_status'];
                                $connectorDebug['status_msg'] = "Category with ".$categoryId." ".$result['exception_desc'];
                                Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();
                                $finalresult[$k] = $result;

                            }
                            else{
                                $mbizParentId = $singledata['ItemDetails']['parent_id'];
                                if($mbizParentId>1)
                                {
                                    $categoryRelationModel = Mage::getModel('mbizcategory/mbizcategory')
                                        ->getCollection()
                                        ->addFieldToFilter('mbiz_id', $mbizParentId)
                                        ->setOrder('id','asc')
                                        ->getData();
                                    if(count($categoryRelationModel)>0)
                                    {
                                        $parentId = $categoryRelationModel[0]['magento_id'];
                                    }
                                    else
                                    {
                                        $parentId=0;
                                    }
                                }
                                else
                                {
                                    $parentId=$mbizParentId;
                                }

                                if($parentId>=1)
                                {
                                    try {
                                        $categoryData['is_active']=1;
                                        $categoryData['include_in_menu']=1;
                                        $categoryData['available_sort_by']='position';
                                        $categoryData['default_sort_by']='position';
                                        if($parentId==1)
                                        {
                                            $categoryData['sync_cat_create']='1';
                                        }
                                        $categoryid = Mage::getModel('Microbiz_Connector_Model_Category_Api')->createCategory($parentId, $categoryData);
                                        $categoryRelationData['instance_id']=1;
                                        $categoryRelationData['magento_id']=$categoryid;
                                        $categoryRelationData['mbiz_id']=$mbizCattId;
                                        $categoryRelationData['is_inventory_category']=0;

                                        Mage::getModel('mbizcategory/mbizcategory')->setData($categoryRelationData)->save();
                                        $result['obj_id']      = $categoryid;
                                        $result['mbiz_obj_id'] = $mbizCattId;
                                        $result['sync_status'] = 'Completed';

                                    }
                                    catch (Mage_Api_Exception $ex) {
                                        $result['sync_status']    = 'Failed';
                                        $result['exception_desc'] = $ex->getMessage();
                                        $result['exception_full_desc'] = $ex->getCustomMessage();
                                        $result['exception_id']   = $ex->getCode();
                                    }
                                    $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                                    $result['obj_id']         = $categoryid;
                                }
                                else {
                                    $mbizCattId     = $singledata['HeaderDetails']['mbiz_obj_id'];
                                    $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                                    $result['obj_id']         = '';
                                    $result['mbiz_obj_id'] = $mbizCattId;
                                    $result['sync_status']    = 'Failed';
                                    $result['exception_desc'] = "Unable to Create Category in Magento no Parent Relation Exists.";
                                    $result['exception_id']   = 0;
                                }
                                $connectorDebug = array();
                                $connectorDebug['instance_id'] = $result['instanceId'];
                                $connectorDebug['status'] = $result['sync_status'];
                                $connectorDebug['status_msg'] = "Category with ".$categoryid." ".$result['exception_desc'];
                                Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();
                                $finalresult[$k] = $result;
                            }

                        }

                        else {
                            $categoryId   = $singledata['HeaderDetails']['obj_id'];
                            $categoryData = $singledata['ItemDetails'];
                            $mbizCattId     = $singledata['HeaderDetails']['mbiz_obj_id'];
                            $category = Mage::getModel('catalog/category')->load($categoryId);
                            $isCatExists = $category->getId();
                            if($isCatExists!='') {
                            try {


                                $MbizParentId = $categoryData['parent_id'];
                                $magCurrentParentId = $category->getParentId();
                                Mage::log("came to category api edit",null,'syncproduct.log');
                                Mage::log("category id ".$categoryId,null,'syncproduct.log');
                                Mage::log("category mag parent id ".$magCurrentParentId,null,'syncproduct.log');
                                Mage::log("category mbiz parent id ".$MbizParentId,null,'syncproduct.log');
                                $categoryParentRelationModel = Mage::getModel('mbizcategory/mbizcategory')
                                    ->getCollection()
                                    ->addFieldToFilter('mbiz_id', $MbizParentId)
                                    ->setOrder('id','asc')
                                    ->getFirstItem()->getData();
                                Mage::log("category parent rel",null,'syncproduct.log');
                                Mage::log($categoryParentRelationModel,null,'syncproduct.log');
                                if(!empty($categoryParentRelationModel))
                                {
                                    $magParentId = $categoryParentRelationModel['magento_id'];
                                    if($magCurrentParentId!=$magParentId)
                                    {
                                        Mage::log("category is moved...",null,'syncproduct.log');
                                        $categoryData['parent_id'] = $magParentId;
                                        $category = Mage::getModel('catalog/category')->load($categoryId);
                                        $category->move($magParentId, null);
                                        //Mage::getModel('Mage_Catalog_Model_Category_Api')->move($categoryId, $magParentId);
                                    }
                                    else {
                                        Mage::log("category is updated",null,'syncproduct.log');
                                        $categoryData['parent_id'] = $magCurrentParentId;
                                    }

                                }
                                else {
                                    Mage::log("category parent rel not exits",null,'syncproduct.log');
                                    $categoryData['parent_id'] = $magCurrentParentId;
                                }

                                $categoryRelExists = Mage::getModel('mbizcategory/mbizcategory')
                                    ->getCollection()
                                    ->addFieldToFilter('magento_id', $categoryId)
                                    ->setOrder('id','asc')
                                    ->getFirstItem()->getData();
                                if($singledata['ItemDetails']['parent_id']==1 && !empty($categoryRelExists) && $magCurrentParentId!=$MbizParentId)
                                {
                                    Mage::log("sub category changed to root in mbiz ",null,'syncproduct.log');
                                    $categoryData['sync_cat_create']='1';
                                    $category = Mage::getModel('catalog/category')->load($categoryId);
                                    $category->move($MbizParentId, null);
                                    //Mage::getModel('Mage_Catalog_Model_Category_Api')->move($categoryId, $MbizParentId);
                                }
                                if($singledata['ItemDetails']['parent_id']==1)
                                {
                                    Mage::log("Root category Imported from mbiz",null,'syncproduct.log');
                                    $categoryData['sync_cat_create']='1';
                                    $categoryData['parent_id']='1';
                                }
                                $OrigCatData = $category->getData();
                                $updatedData = array_merge($OrigCatData,$categoryData);

                                $postDataConfig = array();
                                if(!array_key_exists('default_sort_by',$updatedData))
                                {
                                    //$postDataConfig[0] = 'default_sort_by';
                                    //unset($updatedData['default_sort_by']);
                                    $updatedData['default_sort_by'] = 'position';
                                }
                                if(empty($updatedData['available_sort_by']))
                                {
                                    /*if(empty($postDataConfig))
                                    {
                                        $postDataConfig[0] = 'available_sort_by';
                                    }
                                    else {
                                        $postDataConfig[1] = 'available_sort_by';
                                    }*/
                                    //unset($updatedData['available_sort_by']);
                                    $updatedData['available_sort_by'] = 'position';
                                }
                                if(!empty($postDataConfig))
                                {
                                    $updatedData['use_config'] = $postDataConfig;
                                }
                                Mage::log($updatedData,null,'syncproduct.log');
                                Mage::log($categoryId,null,'syncproduct.log');
                                $categoryid = Mage::getModel('Mage_Catalog_Model_Category_Api')->update($categoryId, $updatedData);
                                if($categoryid)
                                {
                                    $cateRelModel = Mage::getModel('mbizcategory/mbizcategory')->getCollection()
                                        ->addFieldToFilter('magento_id', $categoryId)
                                        ->setOrder('id', 'asc')->getFirstItem()->getData();
                                    $newrelationinfo['magento_id']  = $categoryId;
                                    $newrelationinfo['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
                                    $newrelationinfo['mbiz_id']     = $mbizCattId;
                                    $newrelationinfo['is_inventory_category'] = 0;

                                    if(!$cateRelModel) {
                                        Mage::getModel('mbizcategory/mbizcategory')->setData($newrelationinfo)->save();
                                    }
                                    else {
                                        if($cateRelModel['id'])
                                        {
                                            Mage::getModel('mbizcategory/mbizcategory')->setId($cateRelModel['id'])->setMbizId($mbizCattId)->save();
                                        }

                                    }
                                }
                                $result['obj_id']      = $categoryId;
                                $result['mbiz_obj_id'] = $mbizCattId;
                                $result['sync_status'] = 'Completed';
                            }
                            catch (Mage_Api_Exception $ex) {
                                $result['sync_status']    = 'Failed';
                                $result['exception_desc'] = $ex->getMessage();
                                $result['exception_full_desc'] = $ex->getCustomMessage();
                                $result['exception_id']   = $ex->getCode();
                            }

                            $result['instanceId']  = $singledata['HeaderDetails']['instanceId'];
                            $connectorDebug = array();
                            $connectorDebug['instance_id'] = $result['instanceId'];
                            $connectorDebug['status'] = $result['sync_status'];
                            $connectorDebug['status_msg'] = "Category with ".$categoryId." ".$result['exception_desc'];
                            Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();
                            $finalresult[$k] = $result;

                        }
                        else {
                        $result['sync_status']    = 'Failed';
                        $result['exception_desc'] = 'Category No Longer Exists';
                            $result['obj_id']      = $categoryId;
                            $result['mbiz_obj_id'] = $mbizCattId;
                            $finalresult[$k] = $result;

                    }
                        }
                    }
                    else{

                        $mbizCattId     = $singledata['HeaderDetails']['mbiz_obj_id'];
                        $categoryRelationModel = Mage::getModel('mbizcategory/mbizcategory')
                            ->getCollection()
                            ->addFieldToFilter('mbiz_id', $mbizCattId)
                            ->setOrder('id','asc')
                            ->getFirstItem()->getData();
                        if(count($categoryRelationModel)>0)
                        {
                            $relationId = $categoryRelationModel['id'];
                            $magCatId = $categoryRelationModel['magento_id'];
                            $relationModel = Mage::getModel('mbizcategory/mbizcategory');
                            $relationModel->setId($relationId)->delete();
                            $category = Mage::getModel('catalog/category')->load($magCatId);
                            $category->delete();
                            //$result = array();
                            $result['instanceId']  = $singledata['HeaderDetails']['instanceId'];
                            $result['obj_id']      = $magCatId;
                            $result['mbiz_obj_id'] = $mbizCattId;
                            $result['sync_status'] = 'Completed';
                            $result['exception_desc'] = "Category and Relation Removed Successfully";

                        }
                        else {
                            //$result = array();
                            $result['instanceId']  = $singledata['HeaderDetails']['instanceId'];
                            $result['obj_id']      = '';
                            $result['mbiz_obj_id'] = $mbizCattId;
                            $result['sync_status'] = 'Failed';
                            $result['exception_desc'] = "Category Relation not exists";
                        }

                        $finalresult[$k] = $result;

                    }

                    break;
            }
                Mage::helper('microbiz_connector')->createMbizSyncStatus($k,'Completed');
            }
            else {
                $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                $result['obj_id']         = ($singledata['HeaderDetails']['obj_id']) ? $singledata['HeaderDetails']['obj_id'] : Mage::helper('microbiz_connector')->getObjectRelation($singledata['HeaderDetails']['mbiz_obj_id'],$modelname,'MicroBiz') ;
                $result['mbiz_obj_id']    = $singledata['HeaderDetails']['mbiz_obj_id'];
                $result['sync_status']    = $syncMbizStatusData['sync_status'];
                $result['exception_desc'] = $syncMbizStatusData['status_desc'];
                $result['exception_id']   = '';
                $finalresult[$k] = $result;
            }
        }
        $headerIds = array_keys($finalresult);
        $mbizSyncUpdateModel = Mage::getModel('syncmbizstatus/syncmbizstatus')->getCollection()->addFieldToFilter('sync_header_id',array('in'=>$headerIds));
        $mbizSyncUpdateModel->walk('delete');
        return json_encode($finalresult);
        
    }
    /**
     * Update Product Stock for bulk products
     *
     * @param array of products Inventory information including stores
     * @return array of product ids which are Updated
     * @author KT097
     */
    public function extendedUpdateInventory($inventoryArray)
    {
        register_shutdown_function(array($this, 'mbizFatalErrorHandler'));
        $finalresult    = array();
        $inventoryArray = json_decode($inventoryArray, true);
        ksort($inventoryArray);
        foreach ($inventoryArray as $k => $inventory) {
            $syncMbizStatusData = Mage::helper('microbiz_connector')->checkMbizSyncHeaderStatus($k);
			$inventoryId = "";
            $result         = array();
            $materialId     = $inventory['HeaderDetails']['ref_obj_id'];
            $mbizmaterialId = $inventory['HeaderDetails']['mbiz_ref_obj_id'];
            Mage::unregister('sync_microbiz_status_header_id');
            Mage::register('sync_microbiz_status_header_id',$k);
            if(!$syncMbizStatusData) {

                Mage::helper('microbiz_connector')->createMbizSyncStatus($k,'Pending');
            if (empty($materialId)) {
                $magentorelation = Mage::getModel('mbizproduct/mbizproduct')->getCollection()->addFieldToFilter('mbiz_id', $mbizmaterialId)->getData();
                $materialId      = $magentorelation[0]['magento_id'];
            }
            $modelname           = $inventory['HeaderDetails']['model'];
            $data                = $inventory['ItemDetails'];
            $data['material_id'] = $materialId;
            $data['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();

            if ($modelname == 'ProductInventory') {
                try {
                    $productInventory = Mage::getModel('connector/storeinventory_storeinventory')->getCollection()->addFieldToFilter('material_id', $data['material_id'])->addFieldToFilter('company_id', $data['company_id'])->addFieldToFilter('store_id', $data['store_id'])->addFieldToFilter('stock_type', $data['stock_type'])->getData();
                    // if Not Create Store Inventory else update the inventory. 
                    if ($materialId) {
                        if (empty($productInventory)) {
                            $model       = Mage::getModel('connector/storeinventory_storeinventory')->setData($data)->save();
                            $inventoryId = $model->getId();
                        } else {
                            $inventoryId = $productInventory[0]['storeinventory_id']; //assinging id into inventoryId
                            $model       = Mage::getModel('connector/storeinventory_storeinventory')->load($inventoryId)->setData($data); //setting the inventory data based on stock inventory ID
                            $model->setId($inventoryId)->save(); //saving the model
                        }
                        // Check if store exists, create/update store in the magento 
                        $storemodel = Mage::getModel('connector/storeinventorytotal_storeinventorytotal')->getCollection()->addFieldToFilter('company_id', $data['company_id'])->addFieldToFilter('store_id', $data['store_id'])->getData();
                        
                        if (!count($storemodel)) {
                            $storeinformation                     = array();
                            $storeinformation['store_id']         = $data['store_id'];
                            $storeinformation['company_id']       = $data['company_id'];
                            $storeinformation['store_name']       = $data['store_name'];
                            $storeinformation['company_name']     = $data['company_name'];
                            $storeinformation['store_short_name'] = $data['store_short_name'];
                            $storeinformation['instance_id']      = Mage::helper('microbiz_connector')->getAppInstanceId();
                            Mage::getModel('connector/storeinventorytotal_storeinventorytotal')->setData($storeinformation)->save();
                        }
                        $materialid            = $data['material_id'];

                        $productTotalInventory = Mage::getModel('connector/storeinventory_storeinventory')->getCollection()->addFieldToFilter('material_id', $data['material_id'])->getData();
                        $qtyval                = 0;
                        foreach ($productTotalInventory as $pinventory) {
                            if ($pinventory['stock_type'] == 1) {
                                $qtyval = $qtyval + $pinventory['quantity'];
                            }
                        }
                        $stockItem   = Mage::getModel('cataloginventory/stock_item')->loadByProduct($materialid);

                        
                        $stockItem->setData('qty', $qtyval);
                        if ($qtyval > 0) {
                            $stockItem->setData('is_in_stock', '1');
                        }
                        
                        $stockItem->save();
                        $result['status']          = 'Completed';
                    }
                    else {
                        $result['status']          = 'Failed';
                        $result['exception_desc']  = "Product Not Exists. No Product Relation With MIcroBiz ID ".$mbizmaterialId;
                        $result['exception_id']    = 0;
                    }

                }
                catch (Mage_Api_Exception $ex) {
                    $errormsg = $ex->getMessage();
                    $errormsg .= $ex->getCustomMessage();
                    $result['status']          = 'Failed';
                    $result['exception_desc']  = $errormsg;
                    $result['exception_id']    = 0;
                }
                $result['instanceId']      = $inventory['HeaderDetails']['instanceId'];
                $result['ref_obj_id']      = $materialId;
                $result['mbiz_obj_id']     = $inventory['HeaderDetails']['mbiz_obj_id'];
                $result['mbiz_ref_obj_id'] = $inventory['HeaderDetails']['mbiz_ref_obj_id'];

				$connectorDebug = array();
                $connectorDebug['instance_id'] = $result['instanceId'];
                $connectorDebug['status'] = $result['status'];
                $connectorDebug['status_msg'] = "Inventory for  ".$materialId." with Mbiz Object Id ".$mbizmaterialId."  ".$result['exception_desc'];
                Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();
                $finalresult[$k] = $result;
            }
                Mage::helper('microbiz_connector')->createMbizSyncStatus($k,'Completed');
            }
            else {
                $result['instanceId']     = $inventory['HeaderDetails']['instanceId'];
                $result['ref_obj_id']         = $materialId;
                $result['mbiz_obj_id']    = $inventory['HeaderDetails']['mbiz_obj_id'];
                $result['sync_status']    = ($syncMbizStatusData['sync_status'] == 'Completed') ? 'Completed' : 'Failed';
                $result['status']    = ($syncMbizStatusData['sync_status'] == 'Completed') ? 'Completed' : 'Failed';
                $result['exception_desc'] = $syncMbizStatusData['status_desc'];
                $result['exception_id']   = '';
                $finalresult[$k] = $result;
            }
        }
        $headerIds = array_keys($finalresult);
        $mbizSyncUpdateModel = Mage::getModel('syncmbizstatus/syncmbizstatus')->getCollection()->addFieldToFilter('sync_header_id',array('in'=>$headerIds));
        $mbizSyncUpdateModel->walk('delete');
        return json_encode($finalresult);
    }
    /**
     * Save attribute set relation in relation tables
     *
     * @param array of attributeset relation information
     * @return true on succeess
     * @author KT097
     */
    public function saveAttributesetRelation($attributesetinfo)
    {

        $attributesetinfo = array(
            $attributesetinfo
        );

        foreach ($attributesetinfo as $attributeset) {
            $attributeset['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
            $attributeSetId              = $attributeset['magento_id'];
            $checkAttributeSetRelation   = Mage::helper('microbiz_connector')->checkObjectRelation($attributeSetId, 'AttributeSets');
            if (!$checkAttributeSetRelation) {
                $model = Mage::getModel('mbizattributeset/mbizattributeset')->setData($attributeset)->save();
                $attributeset['status'] = "Attribute Set Relation Saved Successfully".$attributeSetId;
            }
            else {
                $relationdata = Mage::getModel('mbizattributeset/mbizattributeset')->getCollection()->addFieldToFilter('magento_id', $attributeSetId)->setOrder('id', 'asc')->getFirstItem();
                $id = $relationdata['id'];
                Mage::getModel('mbizattributeset/mbizattributeset')->setData($attributeset)->setId($id)->save();
                $attributeset['status'] = "Attribute Set Relation Updated Successfully".$attributeSetId;
            }
			$connectorDebug = array();
            $connectorDebug['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();

            $connectorDebug['status'] = "Completed";
            $connectorDebug['status_msg'] = $attributeset['status'];
            Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();
        }
        return json_encode($attributeset);
    }
    /**
     * Save category relation in relation tables
     *
     * @param array of category relation information
     * @return true on succeess
     * @author KT174
     */
    public function mbizSaveCategoryRelation($categoryInfo)
    {
        $categoryInfo = array(
            $categoryInfo
        );

        $arrcategory =array();
        foreach ($categoryInfo as $arrcategory) {
            $arrResult = array();
            $arrcategory['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
            $categoryId              = $arrcategory['magento_id'];
            $checkCategoryRelation   = Mage::helper('microbiz_connector')->checkObjectRelation($categoryId, 'ProductCategories');
            if (!$checkCategoryRelation) {
                Mage::getModel('mbizcategory/mbizcategory')->setData($arrcategory)->save();
                $arrcategory['status'] = "Category Relation Saved Successfully".$categoryId;
            }
            else {
                $relationData = Mage::getModel('mbizcategory/mbizcategory')->getCollection()->addFieldToFilter('magento_id', $categoryId)->setOrder('id', 'asc')->getFirstItem();
                $id = $relationData['id'];
                Mage::getModel('mbizcategory/mbizcategory')->setData($arrcategory)->setId($id)->save();
                $arrcategory['status'] = "Category Relation Updated Successfully".$categoryId;
            }
            $arrResult['instanceId'] = $arrcategory['instance_id'];
            $connectorDebug = array();
            $connectorDebug['instance_id'] = $arrResult['instanceId'];
            $connectorDebug['status'] = "Completed";
            $connectorDebug['status_msg'] = $arrcategory['status'];
            Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();

        }

        return json_encode($arrcategory);
    }

    /**
     * @params $amount = card amount, $ranges = group of ranges.
     * @param $amount
     * @param $ranges
     * @return true if value exists in the ranges false if not.
     * @author KT174
     * @description This method is used to check whether the card amount is already present in the group of ranges or nt
     */
    public function mbizCheckRangeExists($amount,$ranges)
    {
        $found = false;
        foreach ($ranges as $data) {
            if(is_array($data))
            {
                if ($data['price'] == $amount) {
                    $found = true;
                    break; // no need to loop anymore, as we have found the item => exit the loop
                }
            }

        }
        return $found;

    }

    /**
     * @param $giftRanges
     * @param int $syncAll
     * @internal param \of $array gift card ranges.
     * @return true on success
     * @author KT174
     */
    public function mbizSaveGiftCardRanges($giftRanges,$syncAll=0)
    {
        $sku = Mage::getStoreConfig('connector/settings/giftcardsku');
        $product = Mage::getModel('catalog/product');
        $productId = $product->getIdBySku($sku);
        if($productId)  //check whether product exists with that sku or not
        {
            $giftRanges = json_decode($giftRanges,true);

            $connectorDebug = array();
            $product->load($productId);
            /*if(count($giftRanges)==1)  // if the ranges data is single
            {
                $productOptions = $product->getOptions();
                foreach($giftRanges as $giftCardData)
                {
                    if($giftCardData['card_type']==1) // fixed
                    {
                        $amount = $giftCardData['card_amount'];
                        $title = 'Fixed Amount '.$amount;
                    }
                    else
                    {
                        $title = 'Any Amount';
                        $amount = 0;
                    }

                    if($giftCardData['status']==1)  //if gift card is active
                    {

                        if(count($productOptions)>0)  // if the product has any custom options
                        {
                            $options = $product->getOptions();
                            $isOptionExists = 0;
                            $isOptionValueExists = 0;
                            foreach($options as $option){
                                if($option->getTitle()=='Gift Card')
                                {
                                    $isOptionExists=1;
                                    $optionId = $option->getId();
                                    if($isOptionExists==1 && $syncAll==1)
                                    {
                                        $optionModel = Mage::getModel('catalog/product_option')->load($optionId);
                                        $optionModel->delete();

                                        $option = array(
                                            'title' => 'Gift Card',
                                            'type' => 'radio', // could be drop_down ,checkbox , multiple
                                            'is_require' => 1,
                                            'sort_order' => 0,
                                            'values' => array(array(
                                                'title' => $title,
                                                'price' => $amount,
                                                'price_type' => 'fixed',
                                                'sku' => '',
                                                'sort_order' => 1,
                                            )),
                                        );

                                        $product->setProductOptions(array($option));
                                        $product->setCanSaveCustomOptions(true);
                                        $product->save();
                                        $connectorDebug['status']= $connectorDebug['status'].'Gift Card Ranges Synced Successfully';
                                    }
                                    else{
                                        $values = $option->getValues();
                                        foreach($values as $value ){

                                            if($value->getTitle()==$title)
                                            {
                                                $isOptionValueExists = 1;
                                                $value->setPrice($giftCardData['card_amount']);
                                                $value->setSortOrder(3);
                                                $value->save();
                                                $connectorDebug['status']= $connectorDebug['status'].'Gift Card Range Updated Successfully';
                                            }

                                        }
                                    }


                                }

                            }

                            if($isOptionExists==0)  // if product as cust-options but our cust-option is not available
                            {

                                $connectorDebug['status']= $connectorDebug['status'].'Gift Card Ranges Created Successfully';
                                $option = array(
                                    'title' => 'Gift Card',
                                    'type' => 'radio', // could be drop_down ,checkbox , multiple
                                    'is_require' => 1,
                                    'sort_order' => 0,
                                    'values' => array(array(
                                        'title' => $title,
                                        'price' => $amount,
                                        'price_type' => 'fixed',
                                        'sku' => '',
                                        'sort_order' => 1,
                                    )),
                                );

                                $product->setProductOptions(array($option));
                                $product->setCanSaveCustomOptions(true);
                                $product->save();
                            }
                            else  // if our custom option exists
                            {
                                if($isOptionValueExists==0 && $syncAll==0)   // option exists but value not exists.
                                {

                                    $connectorDebug['status']= $connectorDebug['status'].' Gift Card Range Updated Successfully';
                                    $valueModel = Mage::getModel('catalog/product_option_value');
                                    $valueModel->setTitle($title)
                                        ->setPriceType("fixed")
                                        ->setSortOrder("1")
                                        ->setPrice($amount)
                                        ->setSku("")
                                        ->setOptionId($optionId);
                                    $valueModel->save();
                                    $connectorDebug['status']= $connectorDebug['status'].' New Range Created.';

                                }
                            }

                        }
                        else   // if the product does not have any custom options.
                        {

                            $connectorDebug['status']= $connectorDebug['status'].'Gift Card Ranges Created..';
                            $option = array(
                                'title' => 'Gift Card',
                                'type' => 'radio', // could be drop_down ,checkbox , multiple
                                'is_require' => 1,
                                'sort_order' => 0,
                                'values' => array(array(
                                    'title' => $title,
                                    'price' => $amount,
                                    'price_type' => 'fixed',
                                    'sku' => '',
                                    'sort_order' => 1,
                                )),
                            );

                            $product->setProductOptions(array($option));
                            $product->setCanSaveCustomOptions(true);
                            $product->save();
                        }
                    }
                    else  // if the gift card is not active
                    {
                        $connectorDebug['status']= $connectorDebug['status'].'Gift Card Range Not Available or need to Delete.';
                        $OptionValueId = '';
                        $valueCount =0;
                        foreach($productOptions as $option){
                            if($option->getTitle()=='Gift Card')
                            {
                                $optId = $option->getId();
                                foreach($option->getValues() as $value)
                                {
                                    if($value->getTitle()==$title)
                                    {
                                        $OptionValueId = $value->getId();
                                    }
                                    $valueCount++;
                                }
                            }
                        }
                        if($OptionValueId!='' || $OptionValueId!=0) //if the gift card exists then delete option value.
                        {

                            if($valueCount>1)
                            {
                                $optionValueModel = Mage::getModel('catalog/product_option_value')->load($OptionValueId);
                                $optionValueModel->delete();
                            }
                            else
                            {
                                $optionModel = Mage::getModel('catalog/product_option')->load($optId);
                                $optionModel->delete();
                            }


                        }


                    }

                }
                $product->save();
            }
            else*/ if(!empty($giftRanges)) // if gift card ranges are exists
        {
            $productOptions = $product->getOptions();

            $values = array();
            $i=0;

            foreach($giftRanges as $giftCardData)  // prepare values and title and amount
            {
                if($giftCardData['card_type']==1) // fixed
                {
                    $amount = $giftCardData['card_amount'];
                    $title = 'Fixed Amount '.$amount;
                }
                else  //any amount
                {
                    $title = 'Any Amount';
                    $amount = 0;
                }

                if($giftCardData['status']==1)  //if the giftcard range is active
                {

                    $rangeExists = $this->mbizCheckRangeExists($amount,$values);
                    if(!$rangeExists)
                    {
                        $values[$i]['title'] = $title;
                        $values[$i]['price'] = $amount;
                        $values[$i]['price_type'] = 'fixed';
                        $values[$i]['sku'] = '';
                        $values[$i]['sort_order'] = '1';
                        $i++;
                    }

                }
            }

            if(count($productOptions)>0)  // product have any custom options
            {
                //get option id
                foreach($productOptions as $option)
                {
                    if($option->getTitle()=='Gift Card')
                    {
                        $optionId = $option->getId();
                    }
                }

                if($optionId) //if GiftCard  custom option exists
                {

                    if(!empty($values))
                    {
                        //Mage::log("respnse values",null,'reorder.log');
                        //Mage::log($values,null,'reorder.log');
                        $newRanges = $values;
                        $existingRanges = array();

                        foreach($productOptions as $option)
                        {
                            if($option->getTitle()=='Gift Card')
                            {
                                $x=0;
                                $values = $option->getValues();
                                foreach($values as $value ){
                                    $existingRanges[$x]['title'] = $value->getTitle();
                                    $existingRanges[$x]['price'] = $value->getPrice();
                                    $x++;



                                }
                            }
                        }
                        //Mage::log($existingRanges,null,'reorder.log');
                        foreach($newRanges as $range) {
                            //check the range exists or not.
                            $rangeExists = $this->mbizCheckRangeExists($range['price'],$existingRanges);
                            if(!$rangeExists)  //if the range is not exists in the available options create new value
                            {
                                //Mage::log($range['title'],null,'reorder.log');
                                $valueModel = Mage::getModel('catalog/product_option_value');
                                $valueModel->setTitle($range['title'])
                                    ->setPriceType($range['price_type'])
                                    ->setSortOrder($range['sort_order'])
                                    ->setPrice($range['price'])
                                    ->setSku("")
                                    ->setOptionId($optionId);
                                $valueModel->save();
                            }
                        }

                        $product->save();


                        //Now delete the options which are exists already that are not synced currently.

                        foreach($productOptions as $option)
                        {
                            if($option->getTitle()=='Gift Card')
                            {
                                foreach($values as $key=>$value ){
                                    $price = $value->getPrice();
                                    $rangeExists = $this->mbizCheckRangeExists($price,$newRanges);
                                    if(!$rangeExists)  //range is not present in the currently synced range.
                                    {
                                        //Mage::log("ranges removed".$price,null,'reorder.log');
                                        $optionValueModel = Mage::getModel('catalog/product_option_value')->load($key);
                                        $optionValueModel->delete();
                                    }


                                }
                            }
                        }
                        $product->save();

                        $connectorDebug['status']= $connectorDebug['status'].' Updated the GiftCard Ranges.';
                    }

                }
                else {
                    if(!empty($values))
                    {
                        $option = array(
                            'title' => 'Gift Card',
                            'type' => 'radio', // could be drop_down ,checkbox , multiple
                            'is_require' => 1,
                            'sort_order' => 0,
                            'values' => $values,
                        );

                        $product->setProductOptions(array($option));
                        $product->setCanSaveCustomOptions(true);
                        $product->save();

                        $connectorDebug['status']= $connectorDebug['status'].' created new ranges.';
                    }
                }
            }
            else{ // if the product does not have options and range data is greater than 1
                if(count($values)>0)
                {
                    $option = array(
                        'title' => 'Gift Card',
                        'type' => 'radio', // could be drop_down ,checkbox , multiple
                        'is_require' => 1,
                        'sort_order' => 0,
                        'values' => $values,
                    );

                    $product->setProductOptions(array($option));
                    $product->setCanSaveCustomOptions(true);
                    $product->save();

                    $connectorDebug['status']= $connectorDebug['status'].' created new ranges.';
                }
            }
        }
        else // if the gifCard has no active ranges.
        {
            $connectorDebug['status'].= 'No Active Gift Cards available ';
        }

        }
        else
        {
            $connectorDebug['status']= 'Product Not Available';
        }


        $connectorDebug['instance_id'] = 1;
        Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();

        return json_encode($connectorDebug);

    }

    /**
     * Save product relation in relation tables
     *
     * @param array of product relation information
     * @return true on succeess
     * @author KT097
     */
    public function saveProductRelation($productinfo)
    {

       /* $productinfo = array(
            $productinfo
        );*/
        foreach ($productinfo as $product) {
            $product['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
            $productid              = $product['magento_id'];
            /* $productModel = Mage::getModel("catalog/product")->load($productid);
             $productModel->setSyncPrdCreate(1);
             $productModel->setSyncStatus(1);
             if(isset($product['prd_status'])) {
                 $productModel->setPosProductStatus($product['prd_status']);
             }
             $productModel->save();*/
            unset($product['prd_status']);
            $checkProductRelation   = Mage::helper('microbiz_connector')->checkObjectRelation($productid, 'Product');

            if (!$checkProductRelation) {
                $model = Mage::getModel('mbizproduct/mbizproduct')->setData($product)->save();
            }
            else {
                $relationdata = Mage::getModel('mbizproduct/mbizproduct')->getCollection()->addFieldToFilter('magento_id', $productid)->setOrder('id', 'asc')->getFirstItem();
                $id = $relationdata['id'];
                Mage::getModel('mbizproduct/mbizproduct')->setData($product)->setId($id)->save();
                $attributeset['status'] = "Product Relation Updated Successfully";
            }
			$connectorDebug = array();
            $result = array();
            $connectorDebug['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
            $connectorDebug['status'] = "Completed";
            $connectorDebug['status_msg'] = "product relation Saved ". $productid  ;
            Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();
        }
        return true;
    }
    /**
     * Save Customer relation in relation tables
     *
     * @param array of Customer relation information
     * @return true on succeess
     * @author KT097
     */
    public function saveCustomerRelation($customerinfo)
    {

       /* $customerinfo = array(
            $customerinfo
        );*/

        foreach ($customerinfo as $customer) {
            $customer['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
            $customerid              = $customer['magento_id'];
            $checkCustomerRelation   = Mage::helper('microbiz_connector')->checkObjectRelation($customerid, 'Customer');
            $customerModel = Mage::getModel('customer/customer')->load($customerid);
            $customerModel->setSyncCusCreate(1);
            $customerModel->setSyncStatus(1);
            $customerModel->setPosCusStatus(1);
            $customerModel->save();
            if (!$checkCustomerRelation) {
                Mage::getModel('mbizcustomer/mbizcustomer')->setData($customer)->save();
            }
            else {
                $relationdata = Mage::getModel('mbizcustomer/mbizcustomer')->getCollection()->addFieldToFilter('magento_id', $customerid)->setOrder('id', 'asc')->getFirstItem();
                $id = $relationdata['id'];
                Mage::getModel('mbizcustomer/mbizcustomer')->setData($customer)->setId($id)->save();
                $attributeset['status'] = "Customer Relation Updated Successfully";
            }
			$connectorDebug = array();
            $result = array();
            $connectorDebug['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
            $connectorDebug['status'] = "Completed";
            $connectorDebug['status_msg'] = "Customer relation Saved ". $customerid  ;
            Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();
            
        }
        return true;
    }
    /**
     * Save CustomerAddress relation in relation tables
     *
     * @param array of CustomerAddress relation information
     * @return true on succeess
     * @author KT097
     */
    public function saveCustomerAddressRelation($customerAddressInfo)
    {

      /*  $customerAddressInfo = array(
            $customerAddressInfo
        ); */

        foreach ($customerAddressInfo as $customerAddress) {
            $customerAddress['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
            $customerAddressId              = $customerAddress['magento_id'];
            $checkCustomerAddressRelation   = Mage::helper('microbiz_connector')->checkObjectRelation($customerAddressId, 'CustomerAddressMaster');
            if (!$checkCustomerAddressRelation) {
                Mage::getModel('mbizcustomeraddr/mbizcustomeraddr')->setData($customerAddress)->save();
            }
            else {
                $relationdata = Mage::getModel('mbizcustomeraddr/mbizcustomeraddr')->getCollection()->addFieldToFilter('magento_id', $customerAddressId)->setOrder('id', 'asc')->getFirstItem();
                $id = $relationdata['id'];
                Mage::getModel('mbizcustomeraddr/mbizcustomeraddr')->setData($customerAddress)->setId($id)->save();


            }
			$connectorDebug = array();
            $result = array();
            $connectorDebug['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
            $connectorDebug['status'] = "Completed";
            $connectorDebug['status_msg'] = "Customer Address relation Saved ". $customerAddressId  ;
            Mage::getModel('connectordebug/connectordebug')->setData($connectorDebug)->save();
            
        }
        return true;
    }
    /**
     * Save App Sync Status
     *
     * @param Sync information array (sync status and InstanceId)
     * @return true on succeess
     * @author KT097
     */
    public function setAppSyncStatus($appSyncStatus)
    {
        $instance_id = $appSyncStatus['instance_id'];
        $syncstatus  = $appSyncStatus['syncstatus'];
        $configdata  = new Mage_Core_Model_Config();
        $configdata->saveConfig('connector/settings/instance_id', $instance_id, 'default', 0);
        $configdata->saveConfig('connector/settings/syncstatus', $syncstatus, 'default', 0);
        /*
         * Cleaning the configuration cache programatically
         */
        try {
            $allTypes = Mage::app()->useCache();
            foreach ($allTypes as $type => $blah) {
                Mage::app()->getCacheInstance()->cleanType($type);
            }
        }
        catch (Exception $e) {
            // do something
            error_log($e->getMessage());
        }
        return true;
    }
	
	/**
     * Get Tax Classes
     *
     * 
     * @return tax class array
     * @author KT097
     */
    public function getTaxClasses()
    {
        $taxClasses = Mage::getModel('tax/class')->getCollection()->getData();
		$allTaxclasses=array();
		foreach($taxClasses as $taxClass) {
			$taxclasstype=$taxClass['class_type'];
			$allTaxclasses[$taxclasstype][]=$taxClass;
		}
		return json_encode($allTaxclasses);
    }
	
	/**
     * for Magento orders  
     *
     * @return array of Created orders
     * @author KT097
     */
    public function getOrderDetails()
    {
        
        /*$collection           = array();
        $headerdatacollection = Mage::getModel('saleorderheader/saleorderheader')->getCollection()->setOrder('id', 'asc')->addFieldToFilter('overall_hdr_status', '0')->getData();
        foreach ($headerdatacollection as $headerdata) {
           
            $order_id                               = $headerdata['sal_order_mag_id'];
            $collection[$order_id]['HeaderDetails'] = $headerdata;
            
            $itemdatacollection = Mage::getModel('saleorderitem/saleorderitem')->getCollection()->addFieldToFilter('sal_order_mag_id', $order_id)->getData();
            $itemsData       = array();
            foreach ($itemdatacollection as $itemdata) {
                $itemsData[] = $itemdata;
            }
            
            $collection[$order_id]['ItemDetails'] = $itemsData;
			
			$shipAddressDataCollection = Mage::getModel('saleordershipaddress/saleordershipaddress')->getCollection()->addFieldToFilter('sal_order_mag_id', $order_id)->getData();
            $shipAddress       = array();
            foreach ($shipAddressDataCollection as $shipAddressData) {
                $shipAddress[] = $shipAddressData;
            }
            
            $collection[$order_id]['ShipAddressDetails'] = $shipAddress;
        }
       return json_encode($collection);*/
    }

    /**
     * for Magento orders
     *
     * @param $order_id
     * @return details of an order
     * @author KT097
     */
    public function getOrderinformation($order_id)
    {

        $collection           = array();
        $headerdatacollection = Mage::getModel('saleorderheader/saleorderheader')->getCollection()->setOrder('id', 'asc')->addFieldToFilter('order_id', $order_id)->getFirstItem()->getData();
        $headerdata           = $headerdatacollection;
        //foreach ($headerdatacollection as $headerdata) {

        //$order_id                               = $headerdata['sal_order_mag_id'];
        $headerdata['mage_order_number'] = Mage::getModel('sales/order')->load($order_id)->getIncrementId();
        $collection[$order_id]['OrderHeaderDetails'] = $headerdata;
        $itemdatacollection = Mage::getModel('saleorderitem/saleorderitem')->getCollection()->addFieldToFilter('order_id', $order_id)->getData();
        $itemsData          = array();
        foreach ($itemdatacollection as $itemdata) {

            $giftCardProductSku = Mage::getStoreConfig('connector/settings/giftcardsku');
            if($giftCardProductSku == $itemdata['sku']) {
                $itemdata['item_type'] = 2;
                $gcdDetails = Mage::getModel('mbizgiftcardsale/mbizgiftcardsale')->getCollection()->addFieldToFilter('order_id', $order_id)->addFieldToFilter('order_item_id',$itemdata['order_line_item_id'])->getData();
                $itemdata['gcd_info'] = $gcdDetails;
            }
            $brkupdataPromotioncollection = Mage::getModel('saleorderbrkup/saleorderbrkup')->getCollection()->addFieldToFilter('order_id', $order_id)->addFieldToFilter('order_line_itm_num', $itemdata['order_line_item_id'])->addFieldToFilter('brkup_type_id', 3)->getData();
            $itemdata['promotions'] = $brkupdataPromotioncollection;
            $brkupdataTaxcollection = Mage::getModel('saleorderbrkup/saleorderbrkup')->getCollection()->addFieldToFilter('order_id', $order_id)->addFieldToFilter('brkup_type_id', 1)->addFieldToFilter('order_line_itm_num', $itemdata['order_line_item_id'])->getData();
            $itemdata['tax'] = $brkupdataTaxcollection;
            $itemsData[] = $itemdata;
        }

        $collection[$order_id]['OrderItemDetails'] = $itemsData;
        /*$brkupdatacollection = Mage::getModel('saleorderbrkup/saleorderbrkup')->getCollection()->addFieldToFilter('order_id', $order_id)->getData();
        $brkupData          = array();
        foreach ($brkupdatacollection as $brkup) {
            $brkupData[] = $brkup;
        }

        $collection[$order_id]['BrkupDetails'] = $brkupData;
        */
        $shipmentInfo = Mage::getModel('pickup/pickup')->getCollection()->addFieldToFilter('order_id', $order_id)->setOrder('id','asc')->getData();
        $collection[$order_id]['OrderHeaderDetails']['zone_id'] = $shipmentInfo[0]['zone_id'];

        $addressData = Mage::getModel('sales/order_address')->getCollection()->addFieldToFilter('parent_id',$order_id)->addFieldToFilter('address_type','shipping')->getFirstItem()->getData();

        if(count($addressData)==0) {
            $addressData = Mage::getModel('sales/order_address')->getCollection()->addFieldToFilter('parent_id',$order_id)->addFieldToFilter('address_type','billing')->getFirstItem()->getData();
        }

        $collection[$order_id]['AddressDetails'] = $addressData;
        $order = Mage::getModel("sales/order")->load($order_id); //load order by order id
        $payment_method_code = $order->getPayment()->getMethodInstance()->getCode();
        $creditData = Mage::getModel('mbizcreditusage/mbizcreditusage')->getCollection();
        $arrCreditData = $creditData->addFieldToFilter('order_id',$order_id)->setOrder('id','asc')->getData();
        $discountAmount =0;
        if(count($arrCreditData)>0)
        {
            if(is_array($arrCreditData))
            {

                foreach($arrCreditData as $key=>$data)
                {
                    $discountAmount = $discountAmount + $data['credit_amt'];
                }
            }
        }
        $collection[$order_id]['OrderHeaderDetails']['payment']['total_due'] = $order->getTotalDue();
        $collection[$order_id]['OrderHeaderDetails']['payment']['total_paid'] = $order->getTotalPaid()-$discountAmount;
        $collection[$order_id]['OrderHeaderDetails']['payment']['total_amount'] = $order->getGrandTotal();
        $orderPaymentItemsInformation = array();
        $paymentItemsInformation = Mage::getModel('mbizcreditusage/mbizcreditusage')->getCollection()->addFieldToFilter('order_id',$order_id)->getData();
        foreach($paymentItemsInformation as $paymentItemInformation) {

            $paymentItemInfo = array();
            if($paymentItemInformation['type'] == 1) {
                $paymentItemInfo['method'] = 'mbiz_storecredit';
            }
            if($paymentItemInformation['type'] == 2){
                $paymentItemInfo['method'] = 'mbiz_giftcard';
            }
            $paymentItemInfo['paid_amount'] = $paymentItemInformation['credit_amt'];
            $paymentItemInfo['credit_id'] = $paymentItemInformation['credit_id'];
            $orderPaymentItemsInformation[] = $paymentItemInfo;
        }
        $origpaymentItemInfo['method'] = $payment_method_code;
        $origpaymentItemInfo['paid_amount'] = $order->getTotalPaid();
        $orderPaymentItemsInformation[] = $origpaymentItemInfo;
        $collection[$order_id]['OrderHeaderDetails']['payment']['items'] = $orderPaymentItemsInformation;
        $shipmentInfo = Mage::getModel('pickup/pickup')->getCollection()->addFieldToFilter('order_id', $order->getId())->setOrder('id','asc')->getData();
        $collection[$order_id]['OrderHeaderDetails']['note'] = $shipmentInfo[0]['note'];
        $shippingMethodDetails = array();
        $shippingMethodDetails['method'] = $order->getShippingMethod();
        $shippingMethodDetails['amount'] = $order->getShippingInclTax();
        $collection[$order_id]['OrderHeaderDetails']['shippingDetails'] = $shippingMethodDetails;
        //}
        return $collection;
    }
    public function updateOrderData($ordersData) {

        $orderHeaderModel = Mage::getModel('saleorderheader/saleorderheader')->getCollection()->addFieldToFilter('order_id',$ordersData['OrderHeaderDetails']['order_id'])->getData();
        $orderHeaderModelId = $orderHeaderModel[0]['id'];
        $orderHeaderModelUpdate = Mage::getModel('saleorderheader/saleorderheader')->load($orderHeaderModelId);
        $orderHeaderModelUpdate->setData($ordersData['OrderHeaderDetails'])->setId($orderHeaderModelId)->save();
        /*foreach($ordersData['OrderItemDetails'] as $itemInformation) {
            $itemInformation['order_id'] = $ordersData['OrderHeaderDetails']['order_id'];
            $orderItemsModel = Mage::getModel('saleorderitem/saleorderitem')->getCollection()->addFieldToFilter('order_id',$ordersData['OrderHeaderDetails']['order_id'])->addFieldToFilter('sku',$itemInformation['sku'])->getData();
            $orderItemsModelId = $orderItemsModel[0]['id'];
            $itemInformation['mbiz_order_line_item_id'] = $itemInformation['order_line_item_id'];
            $itemInformation['order_line_item_id'] = $orderItemsModel[0]['order_line_item_id'];
            $itemInformation['order_id'] = $ordersData['OrderHeaderDetails']['order_id'];
            $orderItemsModelUpdate = Mage::getModel('saleorderitem/saleorderitem')->load($orderItemsModelId);
            $orderItemsModelUpdate->setData($itemInformation)->setId($orderItemsModelId)->save();
        }*/

    }

    /**
     * Update attributesets
     * @param Id $attributeSetId
     * @param $attributeSetData
     * @param $mbizAttributeSetId
     * @param bool $isNewlyCreated
     * @internal param \Id $attributeSetId of an attributeSet needs to update
     * @internal param array $attributesetData of attributeset information with groups and Attributes
     * @return array containing status and object id
     * @author KT097
     */
    public function updateAttributeSet($attributeSetId, $attributeSetData, $mbizAttributeSetId, $isNewlyCreated = false)
    {
        $locale = 'en_US';

// changing locale works!

        Mage::app()->getLocale()->setLocaleCode($locale);
        Mage::app()->getTranslator()->init('frontend', true);
        Mage::app()->getTranslator()->init('adminhtml', true);
// needed to add this
        Mage::app()->getTranslator()->setLocale($locale);
        Mage::Log(Mage::app()->getTranslator());
        $exceptions       = array();
        $isNewItemsExists = false;
        unset($attributeSetData['attribute_set_name']);
        $groups    = Mage::getModel('eav/entity_attribute_group')->getResourceCollection()->setAttributeSetFilter($attributeSetId)->load();
        $arrGroups = array();
        foreach ($groups as $group) {
            $arrGroups[] = $group->getAttributeGroupId();
        }

        $newarrGroups = array();
        $mbizGroups   = $attributeSetData;
        //Mage::Log($mbizGroups);
        //return $mbizGroups;
        foreach ($mbizGroups as $value) {
            //$attributeGroup = $key;
            $mbizAttributeGroupId = $value['attribute_group_id'];
            $attributeGroupName   = trim($value['attribute_group_name']);
            Mage::Log($attributeGroupName);
            $checkAttributeGroupExists = Mage::getModel('mbizattributegroup/mbizattributegroup')->getCollection()->addFieldToFilter('mbiz_id', $mbizAttributeGroupId)->getFirstItem()->getData();
            if ($checkAttributeGroupExists) {
                $attributeGroupId = $checkAttributeGroupExists['magento_id'];
            } else {
                $groupData        = Mage::getModel('eav/entity_attribute_group')->getResourceCollection()->setAttributeSetFilter($attributeSetId)->addFieldToFilter('attribute_group_name', $attributeGroupName)->getFirstItem()->getData();
                $attributeGroupId = $groupData['attribute_group_id'];
            }
            if (!$attributeGroupId) {
                $modelGroup = Mage::getModel('eav/entity_attribute_group');
                //set the group name
                $modelGroup->setAttributeGroupName($attributeGroupName)->setAttributeSetId($attributeSetId);
                //save the new group
                $modelGroup->save();
                $attributeGroupId = (int) $modelGroup->getId();
            }
            $newarrGroups[] = $attributeGroupId;
            Mage::Log('Attribute Group Id ');
            Mage::Log($attributeGroupId);
            $attributeGroupRelation['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
            $attributeGroupRelation['magento_id']  = $attributeGroupId;
            $attributeGroupRelation['mbiz_id']     = $mbizAttributeGroupId;
            $checkAttributeGroupRelation           = Mage::getModel('mbizattributegroup/mbizattributegroup')->getCollection()->addFieldToFilter('magento_id', $attributeGroupId)->setOrder('id', 'asc')->getData();

            if (!$checkAttributeGroupRelation) {
                $model            = Mage::getModel('mbizattributegroup/mbizattributegroup')->setData($attributeGroupRelation)->save();
                $isNewItemsExists = true;
            }
            $attributes           = $value['attributes'];
            $attributesCollection = Mage::getResourceModel('catalog/product_attribute_collection');
            $attributesCollection->setAttributeGroupFilter($attributeGroupId);

            $oldAttributeIds = array();
            $userDefinedAttributeIds = array();
            foreach ($attributesCollection as $attributeinformation) {
                $oldAttributeIds[] = $attributeinformation->getAttributeId();
                if($attributeinformation->getIsUserDefined()) {
                    $userDefinedAttributeIds[] = $attributeinformation->getAttributeId();
                }
            }
            //return $oldAttributeIds;
            $newAttributeIds = array();
            foreach ($attributes as $code => $attribute) {
                Mage::Log('Attribute Code Execution for Mbiz Attribute '.$code);
                $attributeId     = '';
                $mbizAttributeId = $attribute['attribute_id'];
                unset($attribute['attribute_id']);
                if (isset($attribute['attribute_options'])) {
                    $attributeOptions = $attribute['attribute_options'];
                    unset($attribute['attribute_options']);
                }
                $attribute['frontend_label'] = $attribute['attribute_label'];
                $mbizAttributeCode           = $code;
                $checkMbizAttributeCode      = Mage::getModel('mbizattribute/mbizattribute')->getCollection()->addFieldToFilter('mbiz_attr_code', $code)->setOrder('id', 'asc')->getData();
                if ($checkMbizAttributeCode) {
                    $code = $checkMbizAttributeCode[0]['magento_attr_code'];
                }
                Mage::Log($code);
                Mage::Log($attribute);
                $checkAttributeExists = Mage::getModel('mbizattribute/mbizattribute')->getCollection()->addFieldToFilter('mbiz_id', $mbizAttributeId)->getFirstItem()->getData();
                if ($checkAttributeExists) {
                    $attributeId = $checkAttributeExists['magento_id'];
                }
                if (!$attributeId) {
                    $isAttributeExists = Mage::getModel('catalog/resource_eav_attribute')->loadByCode('catalog_product', $code);

                    if (!$isAttributeExists->getId()) {
                        Mage::Log('attribute if');
                        try {
                            //$attributeId = Mage::getModel('Mage_Catalog_Model_Product_Attribute_Api')->create($attribute);
                            $attributeId = $this->createAttribute($attribute);
                            if (is_array($attributeId)) {
                                $exceptions  = $attributeId;
                                $attributeId = '';
                            }

                        }
                        catch (Exception $e) {
                            $exceptions[] = $e->getMessage();
                        }
                    } else {
                        Mage::Log('attribute else');
                        $attributeId     = $isAttributeExists->getId();
                        //Mage::getModel('Mage_Catalog_Model_Product_Attribute_Api')->update($isAttributeExists,$attribute);
                        $attributeUpdate = Mage::getModel('catalog/resource_eav_attribute')->load($attributeId);
                        // set frontend labels array with store_id as keys
                        try {
                            $attributeUpdate->setAttributeId($attributeId)->setSourceModel($attributeUpdate->getSourceModel())->setIsConfigurable($attribute['is_configurable'])->setIsGlobal($attributeUpdate->getIsGlobal())->setIsRequired($attribute['is_required'])->setIsUserDefined($attribute['is_user_defined'])->setIsUsedForPromoRules($attribute['is_used_for_promo_rules'])->setIsUnique($attribute['is_unique'])->setFrontendLabel($attribute['frontend_label'])->save();
                            //$attributeUpdateId = Mage::getModel('Mage_Catalog_Model_Product_Attribute_Api')->update($attributeId,$attribute);
                        }
                        catch (Exception $e) {
                            $exceptions[] = "Exception While updating";
                            $exceptions[] = $e->getMessage();
                        }

                    }
                } else {
                    //Mage::getModel('Mage_Catalog_Model_Product_Attribute_Api')->update($isAttributeExists,$attribute);
                    $attributeUpdate = Mage::getModel('catalog/resource_eav_attribute')->load($attributeId);
                    // set frontend labels array with store_id as keys
                    try {
                        $attributeUpdate->setAttributeId($attributeId)->setSourceModel($attributeUpdate->getSourceModel())->setIsConfigurable($attribute['is_configurable'])->setIsGlobal($attributeUpdate->getIsGlobal())->setIsRequired($attribute['is_required'])->setIsUserDefined($attribute['is_user_defined'])->setIsUsedForPromoRules($attribute['is_used_for_promo_rules'])->setIsUnique($attribute['is_unique'])->setFrontendLabel($attribute['frontend_label'])->save();
                        //$attributeUpdateId = Mage::getModel('Mage_Catalog_Model_Product_Attribute_Api')->update($attributeId,$attribute);
                    }
                    catch (Exception $e) {
                        $exceptions[] = $e->getMessage();
                    }
                }
                if ($attributeId) {
                    Mage::Log('test name');
                    $newAttributeIds[]                      = $attributeId;
                    $attributeRelation['instance_id']       = Mage::helper('microbiz_connector')->getAppInstanceId();
                    $attributeRelation['magento_id']        = $attributeId;
                    $attributeRelation['mbiz_id']           = $mbizAttributeId;
                    $attributeRelation['magento_attr_code'] = $code;
                    $attributeRelation['mbiz_attr_code']    = $mbizAttributeCode;
                    $attributeRelation['mbiz_attr_set_id']  = $mbizAttributeSetId;

                    $checkAttributeRelation                 = Mage::getModel('mbizattribute/mbizattribute')->getCollection()->addFieldToFilter('magento_id', $attributeId)->addFieldToFilter('mbiz_attr_set_id', $mbizAttributeSetId)->setOrder('id', 'asc')->getData();

                    if (!$checkAttributeRelation) {
                        $model            = Mage::getModel('mbizattribute/mbizattribute')->setData($attributeRelation)->save();
                        $isNewItemsExists = true;
                    }
                    $attributeModel = Mage::getModel('eav/entity_attribute')->load($attributeId);
                    $isUserDefined = $attributeModel->getIsUserDefined();
                    $attributeSet   = Mage::getModel('eav/entity_attribute_set')->load($attributeSetId);
                    if (!$attributeGroupId) {
                        // define default attribute group id for current attribute set
                        $attributeGroupId = $attributeSet->getDefaultGroupId();
                    }
                    $attributeModel->setAttributeSetId($attributeSet->getId())->loadEntityAttributeIdBySet();
                    $entityAttributeId = $attributeModel->getEntityAttributeId();
                    //$exceptions[] = $entityAttributeId."test";
                    //return $exceptions;
                    try {
                        $sortOrder = 100;
                        //$attributeModel = Mage::getModel('eav/entity_attribute')->load($entityAttributeId);
                        //  $attributeModel->setEntityTypeId($attributeSet->getEntityTypeId())->setAttributeSetId($attributeSetId)->setAttributeGroupId($attributeGroupId)->setSortOrder($sortOrder)->setEntityAttributeId($entityAttributeId)->save();

                        $attSet = Mage::getModel('eav/entity_type')->getCollection()->addFieldToFilter('entity_type_code','catalog_product')->getFirstItem(); // This is because the you adding the attribute to catalog_products entity ( there is different entities in magento ex : catalog_category, order,invoice... etc )
                        $set = Mage::getModel('eav/entity_attribute_set')->load($attributeSet->getId());
                        $setId = $set->getId();
                        //if(($isUserDefined || (!$isUserDefined && !$entityAttributeId)) && $attributeId) {
                        if($isUserDefined && $attributeId) {
                            $newItem = Mage::getModel('eav/entity_attribute');
                            $newItem->setEntityTypeId($attSet->getId())
                                ->setAttributeSetId($attributeSetId)
                                ->setAttributeGroupId($attributeGroupId)
                                ->setAttributeId($attributeId)
                                ->setSortOrder($sortOrder)
                                ->save();
                            $attributeModelUpdate = Mage::getModel('catalog/resource_eav_attribute')->load($attributeId);
                            $attributeSourceModel = Mage::helper('catalog/product')->getAttributeSourceModelByInputType($attributeModelUpdate->getFrontendInput());
                            $attributeSourceModel = ($attributeModelUpdate->getSourceModel()) ? $attributeModelUpdate->getSourceModel() : $attributeSourceModel;
                            $attributeModelUpdate->setAttributeId($attributeId)->setSourceModel($attributeSourceModel)->save();

                        }
                        Mage::Log("Attribute ".$entityAttributeId." Added to Attribute Set ".$attributeSetId." in Attribute Group ".$attributeGroupId);
                    }
                    catch (Exception $e) {
                        $exceptions[] = $e->getMessage();
                    }
                    Mage::Log('attr source Model');
                    Mage::Log($attributeModel->getSourceModel());
                    if (!$checkAttributeRelation && ($attributeModel->getSourceModel() == 'eav/entity_attribute_source_table' ||  is_null($attributeModel->getSourceModel()))) {
                        Mage::Log($attributeOptions);
                        $attributeCode = $attributeModel->getAttributeCode();
                        $this->updateAttributeOptions($attributeCode, $attributeOptions,$mbizAttributeId);

                    }

                }

            }
            foreach($newAttributeIds as $newAttributeId) {
                $attributeModelUpdate = Mage::getModel('catalog/resource_eav_attribute')->load($newAttributeId);
                $attributeSourceModel = Mage::helper('catalog/product')->getAttributeSourceModelByInputType($attributeModelUpdate->getFrontendInput());
                if($attributeModelUpdate->getSourceModel == 'eav/entity_attribute_source_table') {
                    $attributeModelUpdate->setAttributeId($newAttributeId)->setSourceModel($attributeSourceModel)->save();
                }

            }
            $removeAttributes = array_diff($userDefinedAttributeIds, $newAttributeIds);
            Mage::Log('Removed attributes');
            Mage::Log($removeAttributes);
            foreach ($removeAttributes as $removeAttribute) {
                $checkAttributeRelation = Mage::getModel('mbizattribute/mbizattribute')->getCollection()->addFieldToFilter('magento_id', $removeAttribute)->addFieldToFilter('mbiz_attr_set_id', $mbizAttributeSetId)->setOrder('id', 'asc')->getData();
                if ($checkAttributeRelation) {
                    //$attributeIngroup = Mage::getModel('eav/entity_attribute');
                    //$attributeIngroup->getCollection()->addFieldToFilter('attribute_set_id',$attributeSetId)->addFieldToFilter('attribute_id',$removeAttribute);
//$attributeIngroupData = $attributeIngroup->getData();
                    Mage::Log($attributeGroupId);
                    $attribute = Mage::getModel('eav/entity_attribute')->load($removeAttribute);
                    $attribute->setAttributeSetId($attributeSetId)->setAttributeGroupId($attributeGroupId)->loadEntityAttributeIdBySet();
                    //$attribute->getEntityAttributeId();
                    if ($attribute->getEntityAttributeId()) {
                        try {
                            Mage::getmodel('Mage_Catalog_Model_Product_Attribute_Set_Api')->attributeRemove($removeAttribute, $attributeSetId);
                            $attributerelationId = $checkAttributeRelation[0]['id'];
                            $attributeRelModel   = Mage::getModel('mbizattribute/mbizattribute')->load($attributerelationId);
                            $attributeRelModel->delete();
                        }
                        catch (Exception $e) {
                            $exceptions[] = $e->getMessage();
                        }
                    }
                }
            }
            Mage::Log('Removed attributes End');
        }
        $removeAttributegroups = array_diff($arrGroups, $newarrGroups);
        foreach ($removeAttributegroups as $removeAttributegroup) {
            $checkAttributeGroupRelation = Mage::getModel('mbizattributegroup/mbizattributegroup')->getCollection()->addFieldToFilter('magento_id', $removeAttributegroup)->setOrder('id', 'asc')->getData();
            if ($checkAttributeGroupRelation) {
                try {
                    Mage::getmodel('Mage_Catalog_Model_Product_Attribute_Set_Api')->groupRemove($removeAttributegroup);
                    $attributeGrouprelationId = $checkAttributeGroupRelation[0]['id'];
                    $attributegroupRelModel   = Mage::getModel('mbizattributegroup/mbizattributegroup')->load($attributeGrouprelationId);
                    $attributegroupRelModel->delete();
                }
                catch (Exception $e) {
                    $exceptions[] = $e->getMessage() . $removeAttributegroup;
                }

            }
        }
        $attributesetResponseArray                    = array();
        $attributesetResponseArray['isNewItemsExists'] = $isNewItemsExists;
        $attributesetResponseArray['exceptions']      = $exceptions;
        return $attributesetResponseArray;
    }


    /**
     * Add/Update attribute options
     * @param Id $attributeId
     * @param Attibute $newValue
     * @param null $optionId
     * @param $order
     * @param null $is_default
     * @internal param \Id $attributeId of an attribute needs to update with options
     * @internal param \Attibute $newValue option Vallue
     * @return new option Id
     * @author KT097
     */
    public function addAttributeOption($attributeId, $newValue, $optionId = null,$order ,$is_default = null)
    {
        $attribute = Mage::getModel('catalog/resource_eav_attribute')->load($attributeId);
        try {
            //get all the possible attribute values and put them into array
            $mageAttrOptions  = $attribute->getSource()->getAllOptions(false);
            $attrOptions      = array();
            $attrOptionsUpper = array();
            foreach ($mageAttrOptions as $option) {
                $labelVal                    = strtoupper($option['label']);
                $attrOptionsUpper[$labelVal] = $option['value'];
                $origLabelVal                = $option['label'];
                $attrOptions[$origLabelVal]  = $option['value'];
            }

            //if we do not have the attribute value set, then we need to add
            //the new value to the attribute and return the id of the newly created
            //attribute value
            if ($optionId !='') {
                $_optionArr = array(
                    'value' => array(),
                    'order' => array(),
                    'delete' => array()
                );
                foreach ($attrOptions as $label => $value) {
                    //iterate thru old ones
                    if($optionId == $value) {
                        $label = $newValue;
                    }
                    $_optionArr['value'][$value] = array(
                        $label
                    );
                    if(!isset($_optionArr['order'][$value])) {
                        $optionCollection = Mage::getResourceModel('eav/entity_attribute_option_collection')
                            ->setAttributeFilter($attributeId)
                            ->setPositionOrder('desc')
                            ->load();
                        $optionTotalData = $optionCollection->addFieldToFilter('option_id',$value)->getData();
                        $_optionArr['order'][$value] = $optionTotalData[0]['sort_order'];
                    }
                }
                /* $_optionArr['value'][$optionId] = array(
                     $newValue
                 );*/
                $_optionArr['order'][$optionId] = (int) $order;
                if($is_default) {
                    $modelData[] =$optionId;
                }
                else {
                    $modelData[] = $attribute->getDefaultValue();
                }
            } else if (!isset($attrOptionsUpper[strtoupper($newValue)])) {
                //create that option and retrieve the id
                $_optionArr = array(
                    'value' => array(),
                    'order' => array(),
                    'delete' => array()
                );
                foreach ($attrOptions as $label => $value) {
                    //iterate thru old ones
                    $_optionArr['value'][$value] = array(
                        $label
                    );
                    if(!isset($_optionArr['order'][$value])) {
                        $optionCollection = Mage::getResourceModel('eav/entity_attribute_option_collection')
                            ->setAttributeFilter($attributeId)
                            ->setPositionOrder('desc')
                            ->load();
                        $optionTotalData = $optionCollection->addFieldToFilter('option_id',$value)->getData();
                        $_optionArr['order'][$value] = $optionTotalData[0]['sort_order'];
                    }
                }
                //add the new one
                $_optionArr['value']['option_1'] = array(
                    $newValue
                );
                $_optionArr['order']['option_1'] = (int) $order;
                if($is_default) {
                    $modelData[] = 'option_1';
                }
                else {
                    $modelData[] = $attribute->getDefaultValue();
                }
            }
            //set them to the attribute
            Mage::Log($_optionArr);
            $attribute->setOption($_optionArr);
            $attribute->setDefault($modelData);
            //save the attribute
            $attribute->save();

            //get the new id for the value
            $entityType      = Mage::getModel('catalog/product')->getResource()->getEntityType();
            $attribute       = Mage::getModel('eav/config')->getAttribute('catalog_product', $attributeId);
            $mageAttrOptions = $attribute->getSource()->getAllOptions(false);
            $attrOptions     = array();
            foreach ($mageAttrOptions as $option) {
                $attrOptions[$option['label']] = $option['value'];
            }
            //we have the new attribute value added, new ID fetched, now we need to return it
            return $attrOptions[$newValue];


        }
        catch (Exception $ex) {

            $exceptions[] = $ex->getMessage();
            return $exceptions;
        }

    }
    /**
     * Retrieve stores list
     *
     * @return array
     */
    public function storesList()
    {
        // Retrieve stores

        $stores = Mage::app()->getStores();
        // return Mage::getSingleton('api/session')->getUser()->getUsername();
        // Make result array
        $result = array();
        foreach ($stores as $store) {
            $result[] = array(
                'store_id' => $store->getId(),
                'code' => $store->getCode(),
                'website_id' => $store->getWebsiteId(),
                'website_name' => $store->getWebsite()->getName(),
                'group_id' => $store->getGroupId(),
                'group_name' => $store->getGroup()->getName(),
                'store_name' => $store->getName(),
                'sort_order' => $store->getSortOrder(),
                'is_active' => $store->getIsActive()
            );
        }

        return $result;
    }

    /**
     * Create an attribute.
     *
     * For reference, see Mage_Adminhtml_Catalog_Product_AttributeController::saveAction().
     *
     * @param $attributedataInfo
     * @param $values
     * @param $productTypes
     * @param $setInfo
     * @return int|false
     */
    public function createAttribute($attributedataInfo, $values = -1, $productTypes = -1, $setInfo = -1)
    {

        $labelText     = trim($attributedataInfo['attribute_label']);
        $attributeCode = trim($attributedataInfo['attribute_name']);



        if ($values === -1)
            $values = array();

        if ($productTypes === -1)
            $productTypes = array();

        Mage::Log("Creating attribute [$labelText] with code [$attributeCode].");

        //>>>> Build the data structure that will define the attribute. See
        //     Mage_Adminhtml_Catalog_Product_AttributeController::saveAction().

        switch($attributedataInfo['frontend_input']){
            case 'text':
                $backendModel = 'varchar';
                break;
            case 'textarea':
                $backendModel = 'text';
                break;
            case 'date':
                $backendModel = 'datetime';
                break;
            case 'price':
                $backendModel = 'decimal';
                break;
            case 'weee':
                $backendModel = 'text';
                break;
            case 'media_image':
                $backendModel = 'varchar';
                break;
            case 'boolean':
                $backendModel = 'int';
                break;
            case 'multiselect':
                $backendModel = 'text';
                break;
            case 'select':
                $backendModel = 'int';
                break;
            default:
                $backendModel = 'static';
                break;
        }
        if($attributedataInfo['is_configurable']) {
            $isGlobal = 1;
        }
        else {
            $isGlobal = 0;
        }
        $data = array(
            'is_global' => $isGlobal,
            'frontend_input' => $attributedataInfo['frontend_input'],
            'default_value_text' => '',
            'default_value_yesno' => '0',
            'default_value_date' => '',
            'default_value_textarea' => '',
            'is_unique' => $attributedataInfo['frontend_input'],
            'is_required' => $attributedataInfo['is_required'],
            'frontend_class' => '',
            'is_searchable' => '1',
            'is_visible_in_advanced_search' => '1',
            'is_comparable' => '1',
            'is_used_for_promo_rules' => $attributedataInfo['is_used_for_promo_rules'],
            'is_html_allowed_on_front' => '1',
            'is_visible_on_front' => '0',
            'used_in_product_listing' => '0',
            'used_for_sort_by' => '0',
            'is_configurable' => $attributedataInfo['is_configurable'],
            'is_filterable' => '0',
            'is_filterable_in_search' => '0',
            'backend_type' => $backendModel,
            'default_value' => '',
            //'is_user_defined' => $attributedataInfo['is_user_defined']
            'is_user_defined' => 1
        );

        // Now, overlay the incoming values on to the defaults.
// Valid product types: simple, grouped, configurable, virtual, bundle, downloadable, giftcard
        $data['apply_to']       = $productTypes;
        $data['attribute_code'] = $attributeCode;
        $data['frontend_label'] = array(
            0 => $attributedataInfo['frontend_label']
        );

        //<<<<

        //>>>> Build the model.

        $model = Mage::getModel('catalog/resource_eav_attribute');

        $model->addData($data);

        if ($setInfo !== -1) {
            $model->setAttributeSetId($setInfo['SetID']);
            $model->setAttributeGroupId($setInfo['GroupID']);
        }

        $entityTypeID = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
        $model->setEntityTypeId($entityTypeID);

        $model->setIsUserDefined(1);

        //<<<<

        // Save.

        try {
            $model->save();
        }
        catch (Exception $ex) {
            Mage::Log("Attribute [$labelText] could not be saved: " . $ex->getMessage());
            $exceptions[] = "Attribute [$labelText] could not be saved: " . $ex->getMessage();
            return $exceptions;
        }

        $id = $model->getId();

        Mage::Log("Attribute [$labelText] has been saved as ID ($id).");


        return $id;
    }

    public function saveAttributeSetSync($singledata) {
        $attributeSetId       = '';
        $isNewItemsExists     = false;
        $attributeSetResponse = '';
        $mbizAttributeSetId   = $singledata['HeaderDetails']['mbiz_obj_id'];
        $attributeSetData     = $singledata['ItemDetails'];
        //Mage::Log($singledata['ItemDetails']);
        $entityTypeId            = Mage::getModel('catalog/product')->getResource()->getEntityType()->getId(); //product entity type
        if (empty($singledata['HeaderDetails']['obj_id'])) {
            $isNewlyCreated = false;
            $attributeSetName        = $singledata['ItemDetails']['attribute_set_name'];
            if(!$attributeSetName) {
                $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                $result['obj_id']         = $attributeSetId;
                $result['mbiz_obj_id']    = $singledata['HeaderDetails']['mbiz_obj_id'];
                $result['sync_status']    = 'Failed';
                $result['exception_desc'] ='Data Invalid: Attribute Set name Could not be Empty';
                $result['exception_id']   = '';
                return $result;
            }
            $checkAttributeSetExists = Mage::getModel('mbizattributeset/mbizattributeset')->getCollection()->addFieldToFilter('mbiz_id', $mbizAttributeSetId)->getFirstItem()->getData();
            if ($checkAttributeSetExists) {
                $attributeSetId = $checkAttributeSetExists['magento_id'];
            } else {
                $attributeSet   = Mage::getResourceModel('eav/entity_attribute_set_collection')->setEntityTypeFilter($entityTypeId)->addFilter('attribute_set_name', $attributeSetName)->getFirstItem()->getData();
                $attributeSetId = $attributeSet['attribute_set_id'];
                $isNewlyCreated = true;
            }
            if ($attributeSetId) {

                $attributeSetData     = $singledata['ItemDetails'];
                Mage::getModel('eav/entity_attribute_set')
                    ->setEntityTypeId($entityTypeId)->load($attributeSetId)->setAttributeSetName($singledata['ItemDetails']['attribute_set_name'])->setSyncAttrSetCreate(1)->save();
                $attributeSetResponse = $this->updateAttributeSet($attributeSetId, $attributeSetData, $mbizAttributeSetId,true);
                $statusmsg            = "Attributeset updated" . $attributeSet['attribute_set_id'];
            } else {
                try {
                    $defaultattributeset=Mage::getStoreConfig('connector/settings/defaultattributeset');
                    if(!$defaultattributeset) {
                        $defaultattributeset =  Mage::getModel('catalog/product')->getDefaultAttributeSetId();
                    }
                    $skeletonID       = $defaultattributeset;
                    $attributeSetData = $singledata['ItemDetails'];
                    $newAttributeSet  = Mage::getModel('eav/entity_attribute_set')->setEntityTypeId($entityTypeId)->setAttributeSetName($attributeSetName)->setSyncAttrSetCreate(1);
                    if($attributeSetName && $newAttributeSet->validate()) {
                        $newAttributeSet->save();
                        $isNewlyCreated = true;
                        $newAttributeSet->initFromSkeleton($skeletonID)->save();
                        $attributeSetId       = $newAttributeSet->getId();
                        $attributeSetResponse = $this->updateAttributeSet($attributeSetId, $attributeSetData, $mbizAttributeSetId,$isNewlyCreated);
                        $statusmsg            = "Attributeset Created" . $attributeSetId;
                    }
                    else {
                        $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                        $result['obj_id']         = $attributeSetId;
                        $result['mbiz_obj_id']    = $singledata['HeaderDetails']['mbiz_obj_id'];
                        $result['sync_status']    = 'Failed';
                        $result['exception_desc'] ='Data Invalid: Attribute Set name Could not be Empty';
                        $result['exception_id']   = '';
                        return $result;
                    }

                }
                catch (Exception $ex) {
                    $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
                    $result['obj_id']         = $attributeSetId;
                    $result['mbiz_obj_id']    = $singledata['HeaderDetails']['mbiz_obj_id'];
                    $result['sync_status']    = 'Failed';
                    $result['exception_desc'] = $ex->getMessage();
                    $result['exception_id']   = '';
                    return $result;
                }
            }
        } else {
            $attributeSetId       = $singledata['HeaderDetails']['obj_id'];
            $attributeSetData     = $singledata['ItemDetails'];
            $attributeSetResponse = $this->updateAttributeSet($attributeSetId, $attributeSetData, $mbizAttributeSetId,true);
            $statusmsg            = "Attributeset updated" . $attributeSetResponse['attribute_set_id'];
        }
        if ($attributeSetId) {
            Mage::getModel('eav/entity_attribute_set')
                ->setEntityTypeId($entityTypeId)->load($attributeSetId)->setAttributeSetName($singledata['ItemDetails']['attribute_set_name'])->setSyncAttrSetCreate(1)->save();
            $checkAttibuteSetObjectRelation          = Mage::helper('microbiz_connector')->checkObjectRelation($attributeSetId, 'AttributeSets');
            $attributeSetRelationinfo                = array();
            $attributeSetRelationinfo['magento_id']  = $attributeSetId;
            $attributeSetRelationinfo['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
            $attributeSetRelationinfo['mbiz_id']     = $singledata['HeaderDetails']['mbiz_obj_id'];
            if (!$checkAttibuteSetObjectRelation) {
                Mage::getModel('mbizattributeset/mbizattributeset')->setData($attributeSetRelationinfo)->save();
                $isNewItemsExists = true;

            }
            $result['instanceId']  = $singledata['HeaderDetails']['instanceId'];
            $result['obj_id']      = $attributeSetId;
            $result['mbiz_obj_id'] = $singledata['HeaderDetails']['mbiz_obj_id'];
            $exceptionsInfo        = $attributeSetResponse['exceptions'];
            if (!$isNewItemsExists) {
                $isNewItemsExists = $attributeSetResponse['isNewItemsExists'];
            }

            // if ($isNewItemsExists) {
            $result['attribute_set_info'] =   Mage::getModel('Microbiz_Connector_Model_Observer')->saveAttributeSetSyncInfo($attributeSetId);
            //  }

            $result['exception_desc'] = implode(';', $exceptionsInfo);
            $result['sync_status']    = 'Completed';
        } else {
            $result['instanceId']     = $singledata['HeaderDetails']['instanceId'];
            $result['obj_id']         = $attributeSetId;
            $result['mbiz_obj_id']    = $singledata['HeaderDetails']['mbiz_obj_id'];
            $result['sync_status']    = 'Failed';
            $result['exception_desc'] = 'Attribute Set Id Not Exists';
            $result['exception_id']   = '';
        }
        return $result;
    }

    public function updateAttributeOptions($attributeCode,$attributeOptions,$mbizAttributeId) {

        try {
        $sortOrder = array();
        $isDefault = array();
        $newAttributeOptions = array();
        foreach ($attributeOptions as $attributeOption) {
            if(is_array($attributeOption) && $attributeOption['value'] != '') {
                $newAttributeOptions[]           = $attributeOption['value'];
                $sortOrder[$attributeOption['value']] =  $attributeOption['sort_order'];
                $isDefault[$attributeOption['value']] = isset($attributeOption['is_default']) ? $attributeOption['is_default'] : '';
            }
            else {
               // return false;
            }

        }
        $allStores = Mage::app()->getStores();
        $optionValues = array();
        $optionOrder = array();
            $entityTypeId =Mage::getModel('catalog/product')->getResource()->getEntityType()->getId();
           // $entityTypeId = 4;
            $loadAttributeByCode =  Mage::getModel('eav/entity_attribute')->getCollection()->addFieldToFilter('entity_type_id',$entityTypeId)->addFieldToFilter('attribute_code', $attributeCode)->getFirstItem();
        // $loadAttributeByCode       = Mage::getModel('eav/config')->getAttribute('catalog_product', $attributeCode);
        foreach ($allStores as $_eachStoreId => $val)
        {
            $_storeId = Mage::app()->getStore($_eachStoreId)->getId();
            $attributeOptionCollection = Mage::getResourceModel('eav/entity_attribute_option_collection')->setAttributeFilter($loadAttributeByCode->getId())->setStoreFilter($_storeId)->load();
            foreach($attributeOptionCollection as $mageAttrOptions) {
                if($mageAttrOptions->getStoreDefaultValue()) {
                    $optionValues[$mageAttrOptions->getOptionId()][$_storeId] = $mageAttrOptions->getValue();
                }
            }
        }
        $attributeOptionCollection = Mage::getResourceModel('eav/entity_attribute_option_collection')->setAttributeFilter($loadAttributeByCode->getId())->setStoreFilter(0)->load();
        $oldAttributeOptions = array();
        foreach($attributeOptionCollection as $mageAttrOptions) {
            //if (in_array($mageAttrOptions->getValue(), $newAttributeOptions)) {

            $optionValues[$mageAttrOptions->getOptionId()][0] = $mageAttrOptions->getValue();
            $optionOrder[$mageAttrOptions->getOptionId()] = $sortOrder[$mageAttrOptions->getValue()];
            $oldAttributeOptions[] = $mageAttrOptions->getValue();
            if($isDefault[$mageAttrOptions->getValue()]) {
                $data['default'][0] = $mageAttrOptions->getOptionId();
            }
            //}

        }
        $data['option']['value'] = $optionValues;
        $data['option']['order'] = $optionOrder;
        $addedAttributeOptions = array_diff($newAttributeOptions,$oldAttributeOptions);
        $newAttributeOptioncount  = 1;
        foreach($addedAttributeOptions as $addedAttributeOption) {
            $optionValIndex = 'option_'.$newAttributeOptioncount;
            $data['option']['value'][$optionValIndex] = array('0'=>$addedAttributeOption);
            $data['option']['order'][$optionValIndex] = $sortOrder[$addedAttributeOption];
            if($isDefault[$addedAttributeOption]) {
                $data['default'][0] = $optionValIndex;
            }
            $newAttributeOptioncount++;
        }



        $attributeModel = Mage::getModel('catalog/resource_eav_attribute');

        $attributeModel->load($loadAttributeByCode->getId());

        if(!isset($data['default'][0])) {
            $data['default'][0] = $attributeModel->getDefaultValue();
        }
        $attributeModel->addData($data);
        Mage::Log($data);
        //$attributeModel->setDefault($defaultValue);
        $attributeModel->save();
        $attributeOptionCollection = Mage::getResourceModel('eav/entity_attribute_option_collection')->setAttributeFilter($loadAttributeByCode->getId())->setStoreFilter($loadAttributeByCode->getStoreId())->load();

        foreach ($attributeOptionCollection as $attributeExistsOption) {
            //print_r($attributeExistsOption);
            if (!in_array($attributeExistsOption->getValue(), $newAttributeOptions)) {
                $attributeExistsOption->delete();
            }
        }
        foreach ($attributeOptions as $attributeOption) {
            $optionValue    = $attributeOption['value'];
            $productModel = Mage::getModel('catalog/product');
            $attr = $productModel->getResource()->getAttribute($attributeCode);
            $magentoOptionId = '';
            if ($attr->usesSource() && $optionValue != '') {
                $magentoOptionId = $attr->getSource()->getOptionId($optionValue);
            }
            $checkAttributeOptionRelation = Mage::getModel('mbizattributeoption/mbizattributeoption')->getCollection()->addFieldToFilter('magento_id', $magentoOptionId)->addFieldToFilter('mbiz_attr_id', $mbizAttributeId)->setOrder('id', 'asc')->getData();

            if (!$checkAttributeOptionRelation && $magentoOptionId) {
                $attributeOptionRelation                 = array();
                $attributeOptionRelation['magento_id']   = $magentoOptionId;
                $attributeOptionRelation['mbiz_id']      = $attributeOption['option_id'];
                $attributeOptionRelation['mbiz_attr_id'] = $mbizAttributeId;
                Mage::getModel('mbizattributeoption/mbizattributeoption')->setData($attributeOptionRelation)->save();

            }
        }
        }
        catch (Mage_Core_Exception $e) {
          return false;
        }

        $result = array();
        $optionsInformation = Mage::getModel('Microbiz_Connector_Model_Entity_Attribute_Option_Api')->items($loadAttributeByCode->getId());
        $attributeInformation = $attributeModel->getData();
        $attributeInformation['attribute_options'] = $optionsInformation;
        $result['attribute_info'] = $attributeInformation;
        return $result;
    }
    /*
    * function for handling fatal errors on Create or update Mbiz data
     */
    public function mbizFatalErrorHandler() {

        $error = error_get_last();
        $headerId = Mage::registry('sync_microbiz_status_header_id');
        if($error['type'] == 1) {


            $errorDesc = "FatalError : ".$error['message']."  in file  ".$error['file']."  on line number".$error['line'];

            Mage::helper('microbiz_connector')->createMbizSyncStatus($headerId,'Failed',$errorDesc);
        }

        return true;
    }
    /*
       * function for handling fatal errors on sending header records or update header status in Magento
    */
    public function mbizUpdateFatalErrorHandler() {

        $error = error_get_last();
        if($error['type'] == 1) {

            $headerId = Mage::registry('sync_magento_status_header_id');
            $errorDesc = "FatalError : ".$error['message']."  in file  ".$error['file']."  on line number".$error['line'];
            $origData = Mage::getModel('extendedmbizconnector/extendedmbizconnector')->getCollection()->addFieldToFilter('header_id', $headerId)->getFirstItem()->getData();

            $origData['status']         = "Failed";

            $origData['exception_desc'] = $errorDesc;
            Mage::getModel('extendedmbizconnector/extendedmbizconnector')->load($origData['header_id'])->setData($origData)->save();

            Mage::helper('microbiz_connector')->createMbizSyncStatus($headerId,'Failed',$errorDesc);
        }

        return true;
    }

    public function updateAttributeSetOnImport($attributeSetInfo,$magentoId,$mbizId) {
        $attributeSetData['HeaderDetails']['mbiz_obj_id'] = $mbizId;
        $attributeSetData['HeaderDetails']['obj_id'] = $magentoId;
        $attributeSetData['HeaderDetails']['instanceId'] = Mage::helper('microbiz_connector')->getAppInstanceId();;
        $attributeSetData['ItemDetails'] = $attributeSetInfo;
        $this->saveAttributeSetSync($attributeSetData);
        return true;
    }

}
