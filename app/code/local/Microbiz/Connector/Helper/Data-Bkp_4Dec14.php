<?php
//version 110
class Microbiz_Connector_Helper_Data extends Mage_Core_Helper_Abstract
{


   public function testConnection($postdata){

       $url=$postdata['[mbiz_sitename'];
       $api_user=$postdata['mbiz_username'];
       $api_key=$postdata['mbiz_password'];
       $url= 'http://ktc13.ktree.org/syncattributeset';
       $url    = $url.'/index.php/api/mbizInstanceTestConnection';			// prepare url for the rest call
       $method = 'POST';
       $headers = array(
           'Accept: application/json',
           'Content-Type: application/json',
           'X_MBIZPOS_USERNAME: '.$api_user,
           'X_MBIZPOS_PASSWORD: '.$api_key
       );

       $data=array();
       $handle = curl_init();		//curl request to create the product
       curl_setopt($handle, CURLOPT_URL, $url);
       curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
       curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
       curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
       curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

       switch ($method) {
           case 'GET':
               break;

           case 'POST':
               curl_setopt($handle, CURLOPT_POST, true);
               curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
               break;

           case 'PUT':
               curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'PUT');	// create product request.
               curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
               break;

           case 'DELETE':
               curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'DELETE');
               break;
       }

       $response = curl_exec($handle);	// send curl request to microbiz
       $response=json_decode($response,true);
       $code = curl_getinfo($handle);
       $jsonresponse=array();
       if($code['http_code'] == 200 ) {
           $jsonresponse['status'] = 'SUCCESS';
           $jsonresponse['message'] = $this->__('Test Connection Success With Microbiz Instance');
       }
       else if($code['http_code'] == 500) {
           $jsonresponse['status'] = 'ERROR';
           $jsonresponse['message'] = $code['http_code'].' - Internal Server Error'.$response['message'];
       }
       else if($code['http_code'] == 0) {
           $jsonresponse['status'] = 'ERROR';
           $jsonresponse['message'] = $code['http_code'].' - Please Check the API Server URL'.$response['message'];
       }
       else
       {
           $jsonresponse['status'] = 'ERROR';
           $jsonresponse['message'] = $code['http_code'].' - '.$response['message'];
       }
       return $jsonresponse;
   }
    /*
     * helper function for getting Api information from Plugin configuration
     * @return array of config information
     * @author KT097
     **/
    public function getApiDetails()
    {
        
        $apiInformation                 = array();
        $apiInformation['api_server']   = Mage::getStoreConfig('connector/settings/api_server');
        $apiInformation['instance_id']  = Mage::getStoreConfig('connector/settings/instance_id');
        $apiInformation['api_user']     = Mage::getStoreConfig('connector/settings/api_user');
        $apiInformation['api_key']      = Mage::getStoreConfig('connector/settings/api_key');
        $apiInformation['display_name'] = Mage::getStoreConfig('connector/settings/display_name');
        $apiInformation['syncstatus']   = Mage::getStoreConfig('connector/settings/syncstatus');
        return $apiInformation;
    }
    /*
     * helper function for getting Batch Size information from configuration
     * @return batch size of config information
     * @author KT097
     **/
    public function getBatchSize()
    {
        
        $batchSize = Mage::getStoreConfig('connector/batchsizesettings/batchsize');
        return $batchSize;
    }
    
    /*
     * helper function for Check the object relation  exists in mbiz rel tables
     * @param objectId it will hold the respective id of product/customer/customer address/attributeset
     * @param objectType. It is the value which model we are finding the  relation
     * @return true if relation exists
     * @author KT097
     **/
    public function checkObjectRelation($objectId, $objectType)
    {
        switch ($objectType) {
            case 'Product':
                $relationdata = Mage::getModel('mbizproduct/mbizproduct')->getCollection()->addFieldToFilter('magento_id', $objectId)->setOrder('id', 'asc')->getData();
                break;
            case 'Customer':
                $relationdata = Mage::getModel('mbizcustomer/mbizcustomer')->getCollection()->addFieldToFilter('magento_id', $objectId)->setOrder('id', 'asc')->getData();
                break;
            case 'CustomerAddressMaster':
                $relationdata = Mage::getModel('mbizcustomeraddr/mbizcustomeraddr')->getCollection()->addFieldToFilter('magento_id', $objectId)->setOrder('id', 'asc')->getData();
                break;
            case 'AttributeSets':
                $relationdata = Mage::getModel('mbizattributeset/mbizattributeset')->getCollection()->addFieldToFilter('magento_id', $objectId)->setOrder('id', 'asc')->getData();
                break;
            case 'Attributes':
                $relationdata = Mage::getModel('mbizattribute/mbizattribute')->getCollection()->addFieldToFilter('magento_id', $objectId)->setOrder('id', 'asc')->getData();
                break;
            case 'ProductCategories':
                $relationdata = Mage::getModel('mbizcategory/mbizcategory')->getCollection()->addFieldToFilter('magento_id', $objectId)->setOrder('id', 'asc')->getData();
                break;
        }
        if (!count($relationdata)) {
            return false;
        }
        return true;
    }
    /*
     * helper function for getting App instance id from configuration
     * @return instance id
     * @author KT097
     **/
    public function getAppInstanceId()
    {
        
        $instance_id = Mage::getStoreConfig('connector/settings/instance_id');
        return $instance_id;
    }
    /*
     * helper function for Deleting App relations and store inventory
     * @param objectId it will hold the respective id of product/customer/customer address/attributeset
     * @param objectType. It is the value which model we are deleting relation
     * @return true if success
     * @author KT097
     **/
    public function deleteAppRelation($objectId, $objectType)
    {
        $relation = $this->checkObjectRelation($objectId, $objectType);
        switch ($objectType) {
            
            case 'Product':
                
                if ($relation) {
                    
                    $relationdata = Mage::getModel('mbizproduct/mbizproduct')->getCollection()->addFieldToFilter('magento_id', $objectId)->setOrder('id', 'asc')->getData();
                    $id           = $relationdata[0]['id'];
                    $model        = Mage::getModel('mbizproduct/mbizproduct')->load($id);
                    try {
                        $model->delete();
                    }
                    catch (Mage_Core_Exception $e) {
                        $this->_fault('not_deleted', $e->getMessage());
                        // Some errors while deleting.
                    }
                    
                    $productInventorys = Mage::getModel('connector/storeinventory_storeinventory')->getCollection()->addFieldToFilter('material_id', $objectId)->getData();
                    foreach ($productInventorys as $productInventory) {
                        $inventoryId = $productInventory['storeinventory_id'];
                        Mage::getModel('Microbiz_Connector_Model_Storeinventory_Api')->deleteMbizInventory($inventoryId);
                    }
                    
                }
                break;
            case 'Customer':
                if ($relation) {
                    $relationdata = Mage::getModel('mbizcustomer/mbizcustomer')->getCollection()->addFieldToFilter('magento_id', $objectId)->setOrder('id', 'asc')->getData();
                    $id           = $relationdata[0]['id'];
                    $model        = Mage::getModel('mbizcustomer/mbizcustomer')->load($id);
                    try {
                        $model->delete();
                    }
                    catch (Mage_Core_Exception $e) {
                        $this->_fault('not_deleted', $e->getMessage());
                        // Some errors while deleting.
                    }
                }
                break;
            case 'CustomerAddressMaster':
                if ($relation) {
                    $relationdata = Mage::getModel('mbizcustomeraddr/mbizcustomeraddr')->getCollection()->addFieldToFilter('magento_id', $objectId)->setOrder('id', 'asc')->getData();
                    $id           = $relationdata[0]['id'];
                    $model        = Mage::getModel('mbizcustomeraddr/mbizcustomeraddr')->load($id);
                    try {
                        $model->delete();
                    }
                    catch (Mage_Core_Exception $e) {
                        $this->_fault('not_deleted', $e->getMessage());
                        // Some errors while deleting.
                    }
                }
                break;
            case 'AttributeSets':
                if ($relation) {
                    $relationdata = Mage::getModel('mbizattributeset/mbizattributeset')->getCollection()->addFieldToFilter('magento_id', $objectId)->setOrder('id', 'asc')->getData();
                    $id           = $relationdata[0]['id'];
                    $model        = Mage::getModel('mbizattributeset/mbizattributeset')->load($id);
                    try {
                        $model->delete();
                    }
                    catch (Mage_Core_Exception $e) {
                        $this->_fault('not_deleted', $e->getMessage());
                        // Some errors while deleting.
                    }
                }
                break;
            case 'Attributes':
                if ($relation) {
                    $relationdata = Mage::getModel('mbizattribute/mbizattribute')->getCollection()->addFieldToFilter('magento_id', $objectId)->setOrder('id', 'asc')->getData();
                    $id           = $relationdata[0]['id'];
                    $model        = Mage::getModel('mbizattribute/mbizattribute')->load($id);
                    try {
                        $model->delete();
                    }
                    catch (Mage_Core_Exception $e) {
                        $this->_fault('not_deleted', $e->getMessage());
                        // Some errors while deleting.
                    }
                }
                break;
        }
        return true;
    }
    /**
     * For adding Simple Product to configurable Products
     * @param productId it will hold the simple product Id
     * @param associated_configurable_products array contaning the Magento configurable product ids
     * @return true on success
     * @author KT097
     */
    public function assignSimpleProductToConfigurable($productId, $configurableProducts)
    {
        try {
			foreach ($configurableProducts as $configurableProduct) {
				$config_product          = Mage::getModel('catalog/product')->load($configurableProduct);
                if( $config_product->getTypeId() == 'configurable') {
				$productAttributeOptions = $config_product->getTypeInstance(true)->getConfigurableAttributesAsArray($config_product);
				foreach ($productAttributeOptions as $productAttributeOption) {
					$configAttributes[] = $productAttributeOption['attribute_code'];
				}
				$simpleProductsId   = Mage::getModel('catalog/product_type_configurable')->setProduct($config_product)->getUsedProductCollection()->addAttributeToSelect('*')->addFilterByRequiredOptions()->getAllIds();
				$simpleProductsId[] = $productId;
				 $simpleProductsId = array_unique( $simpleProductsId);
				foreach ($simpleProductsId as $simpleProductId) {
					$simpleProduct    = Mage::getModel('catalog/product')->load($simpleProductId);
					$attributes       = $simpleProduct->getAttributes();
					$simpleconfiginfo = array();
					foreach ($configAttributes as $configAttribute) {
						$attributeValue = null;
						if (array_key_exists($configAttribute, $attributes)) {
							$attributesobj  = $attributes["{$configAttribute}"];
							$attributeValue = $attributesobj->getFrontend()->getValue($simpleProduct);
						}
						$attribute_details = Mage::getSingleton("eav/config")->getAttribute("catalog_product", $configAttribute);
						$options           = $attribute_details->getSource()->getAllOptions(false);
						$eavAttribute      = new Mage_Eav_Model_Mysql4_Entity_Attribute();
						$code              = $eavAttribute->getIdByCode('catalog_product', $configAttribute);
						foreach ($options as $option) {
							if ($option["label"] == $attributeValue) {
								$attributeValueIndex = $option["value"];
							}
						}
						
						$simpleconfiginfo[] = array(
							'label' => $attributeValue,
							'attribute_id' => $code,
							'value_index' => $attributeValueIndex
						);
					}
					$configurableProductData[$simpleProductId] = $simpleconfiginfo;
				}
				$productData['configurable_products_data'] = $configurableProductData;
				$config_product->setConfigurableProductsData($productData['configurable_products_data']);
				$config_product->save();
			}
		}
		}
		catch (Exception $ex) {
			$exceptionArray=array();
			$exceptionArray['exception_desc']=$ex->getMessage();
			return $exceptionArray;
		}
        return true;
    }
    /**
     * For removing Simple Products from configurable Products
     * @param productId it will hold the simple product Id
     * @param associated_configurable_products array contaning the Magento configurable product ids
     * @return true on success
     * @author KT097
     */
    public function removeSimpleProductFromConfigurable($productId, $configurableProducts)
    {
        try {
			foreach ($configurableProducts as $configurableProduct) {
				$config_product          = Mage::getModel('catalog/product')->load($configurableProduct);
                if( $config_product->getTypeId() == 'configurable') {
				$productAttributeOptions = $config_product->getTypeInstance(true)->getConfigurableAttributesAsArray($config_product);
				foreach ($productAttributeOptions as $productAttributeOption) {
					$configAttributes[] = $productAttributeOption['attribute_code'];
				}
				$simpleProductsId = Mage::getModel('catalog/product_type_configurable')->setProduct($config_product)->getUsedProductCollection()->addAttributeToSelect('*')->addFilterByRequiredOptions()->getAllIds();

				foreach ($simpleProductsId as $simpleProductId) {
					if ($productId != $simpleProductId) {
						$simpleProduct    = Mage::getModel('catalog/product')->load($simpleProductId);
						$attributes       = $simpleProduct->getAttributes();
						$simpleconfiginfo = array();
						foreach ($configAttributes as $configAttribute) {
							$attributeValue = null;
							if (array_key_exists($configAttribute, $attributes)) {
								$attributesobj  = $attributes["{$configAttribute}"];
								$attributeValue = $attributesobj->getFrontend()->getValue($simpleProduct);
							}
							$attribute_details = Mage::getSingleton("eav/config")->getAttribute("catalog_product", $configAttribute);
							$options           = $attribute_details->getSource()->getAllOptions(false);
							$eavAttribute      = new Mage_Eav_Model_Mysql4_Entity_Attribute();
							$code              = $eavAttribute->getIdByCode('catalog_product', $configAttribute);
							foreach ($options as $option) {
								if ($option["label"] == $attributeValue) {
									$attributeValueIndex = $option["value"];
								}
							}
							
							$simpleconfiginfo[] = array(
								'label' => $attributeValue,
								'attribute_id' => $code,
								'value_index' => $attributeValueIndex
							);
						}
						$configurableProductData[$simpleProductId] = $simpleconfiginfo;
					}
				}
				$productData['configurable_products_data'] = $configurableProductData;
				$config_product->setConfigurableProductsData($productData['configurable_products_data']);
				$config_product->save();
			}
		}
        }
		catch (Exception $ex) {
			$exceptionArray=array();
			$exceptionArray['exception_desc']=$ex->getMessage();
			return $exceptionArray;
		}
        return true;
    }
    /**
     * For adding/removing Simple Products into configurable Products
     * @param productId it will hold the simple product Id
     * @param associated_configurable_products array contaning the MBiz configurable product ids
     * @return true on success
     * @author KT097
     */
    public function saveSimpleConfig($productId, $associated_configurable_products)
    {
        $oldParentIds = Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($productId);
        $newParentIds = array();
        foreach ($associated_configurable_products as $associated_configurable_product) {
            $relationproductdata = Mage::getModel('mbizproduct/mbizproduct')->getCollection()->addFieldToFilter('mbiz_id', $associated_configurable_product)->setOrder('id', 'asc')->getData();
            if ($relationproductdata) {
                $newParentIds[] = $relationproductdata[0]['magento_id'];
            }
        }
        if (count($newParentIds)) {
            $saveConfigReturn=Mage::helper('microbiz_connector')->assignSimpleProductToConfigurable($productId, $newParentIds);
			if(is_array($saveConfigReturn)) {
				return $saveConfigReturn;
			}
        }
        $removedIds = array_diff($oldParentIds, $newParentIds);
        if (count($removedIds)) {
            $removeConfigReturn=Mage::helper('microbiz_connector')->removeSimpleProductFromConfigurable($productId, $removedIds);
			if(is_array($removeConfigReturn)) {
				return $removeConfigReturn;
			}
        }
        return true;
        
    }
    /**
     * @author KT174
     * @description This method is used to generate  alphanumeric string based on the length passed
     * @return-  alphanumeric string.
     */
    public function mbizGenerateUniqueString($length)
    {
        Mage::log("came to setup file");
        $key = '';
        $keys = array_merge(range(0, 9), range('A', 'Z'));

        for ($i = 0; $i < $length; $i++) {
            $key .= $keys[array_rand($keys)];
        }

        return $key;
    }
    /**
     * @author KT174
     * @description This method is used to generate  alphanumeric username for creating Api User from the plugin
     * installation wizard
     * @return-  alphanumeric string.
     */
    public function mbizGenerateApiUserName($length)
    {
        // Set allowed chars
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

        // Create username
        $username = "";
        for ( $i = 0; $i < $length; $i++ )
        {
            $username .= $chars[mt_rand(0, strlen($chars))];
        }
        return $username;
    }
    /**
     * @author KT174
     * @description This method is used to generate  alphanumeric password for Api User from the plugin
     * installation wizard
     * @return-  alphanumeric string.
     */
    public function mbizGenerateApiPassword($length) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?";
        $password = substr( str_shuffle( $chars ), 0, $length );
        return $password;
    }

    /**
     * @author KT174
     * @description This method is used to get the Initial Sync Details from MicroBiz.
     * @params mbizSitename,mbizApiUserName, mbizApiPassword
     * @return an json Object with the Initial Sync Details.
     */
    public function mbizGetInitialSyncData($sitename,$apiUsername,$apiPassword)
    {
        $apiserver = $sitename.'.microbiz.com';
        $apiserver = 'http://ktc13.ktree.org/syncattributeset';
        $url    = $apiserver.'/index.php/api/mbizInitialSyncDetails';			// prepare url for the rest call
        $method = 'GET';
        $apipath = $apiUsername;
        $apipassword = $apiPassword;

        // headers and data (this is API dependent, some uses XML)
        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'X_MBIZPOS_USERNAME: '.$apipath,
            'X_MBIZPOS_PASSWORD: '.$apipassword
        );
        $data = array();
        $data['instance_id']=1;
        $data['syncstatus']=1;
        $handle = curl_init();		//curl request to create the product
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

        switch ($method) {
            case 'GET':
                break;

            case 'POST':
                curl_setopt($handle, CURLOPT_POST, true);
                //curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
                break;

            case 'PUT':
                curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'PUT');	// create product request.
                //curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
                break;

            case 'DELETE':
                curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($handle);	// send curl request to microbiz
        $code = curl_getinfo($handle);
        return $response;
    }
    /**
     * @author KT174
     * @description This method is used to send the Initial Sync Settings Details to MicroBiz.
     * @params settings array
     * @return an http code.
     */
    public function mbizSendSettings($sitename,$apiUsername,$apiPassword,$mbizSaveSettings)
    {
        Mage::log("came to mbizsendsettings",null,'linking.log');
        Mage::log($mbizSaveSettings,null,'linking.log');
        Mage::log(json_encode($mbizSaveSettings),null,'linking.log');

        $apiserver = $sitename.'.microbiz.com';
        $apiserver = 'http://ktc13.ktree.org/syncattributeset';
        $url    = $apiserver.'/index.php/api/mbizInitialSyncDetails';			// prepare url for the rest call
        $method = 'POST';
        $apipath = $apiUsername;
        $apipassword = $apiPassword;

        // headers and data (this is API dependent, some uses XML)
        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'X_MBIZPOS_USERNAME: '.$apipath,
            'X_MBIZPOS_PASSWORD: '.$apipassword
        );
        $data = array();
        $data = json_encode($mbizSaveSettings);
        $handle = curl_init();		//curl request to create the product
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

        switch ($method) {
            case 'GET':
                break;

            case 'POST':
                curl_setopt($handle, CURLOPT_POST, true);
                curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
                break;

            case 'PUT':
                curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'PUT');	// create product request.
                curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
                break;

            case 'DELETE':
                curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($handle);	// send curl request to microbiz
        $code = curl_getinfo($handle);
        Mage::log($response,null,'linking.log');
        Mage::log($code,null,'linking.log');
        return $code;
    }

    /**
     * @author KT174
     * @description This method is used to sync the sync records to Header Table while the Initial Sync.
     * @params $obj_id,$model_name
     */
    public function mbizInitialSyncHeaderDetails($objId,$modelName,$refObjId=null)
    {
        $user = Mage::getSingleton('admin/session')->getUser()->getFirstname();
        $date = date("Y/m/d H:i:s", Mage::getModel('core/date')->timestamp(time()));
        $attributeSetData['model_name']=$modelName;
        $attributeSetData['instance_id']=Mage::helper('microbiz_connector')->getAppInstanceId();
        $attributeSetData['obj_id']=$objId;
        $attributeSetData['ref_obj_id']=$refObjId;
        $attributeSetData['created_by']=$user;
        $attributeSetData['created_time']= $date;
        $attributeSetData['is_initial_sync']= '1';
        $model = Mage::getModel('extendedmbizconnector/extendedmbizconnector')
            ->setData($attributeSetData)
            ->save();
         $headerId=$model['header_id'];

        return $headerId;
    }

    /**
     * @param $itemId
     * @param $orderId
     * @return array
     * @author KT174
     * @description This method is used to get the itemid,orderid and return the giftcard sale information.
     */
    public function getGcdDetails($itemId,$orderId)
    {
        $gcdDetails = array();
        $gcdDetails = Mage::getModel('mbizgiftcardsale/mbizgiftcardsale')->getCollection()
            ->addFieldToFilter('order_id', $orderId)
            ->addFieldToFilter('order_item_id',$itemId)
            ->getData();
        return $gcdDetails;
    }
/*
 * function to get Object relation
 */
    public function getObjectRelation($objectId, $objectType,$instance = 'Magento')
    {
        $fieldValue = ($instance == 'Magento') ? 'magento_id' : 'mbiz_id';
        $relationdata = '';
        switch ($objectType) {
            case 'Product':
                $relationdata = Mage::getModel('mbizproduct/mbizproduct')->getCollection()->addFieldToFilter($fieldValue, $objectId)->setOrder('id', 'asc')->getFirstItem()->getData();
                break;
            case 'Customer':
                $relationdata = Mage::getModel('mbizcustomer/mbizcustomer')->getCollection()->addFieldToFilter($fieldValue, $objectId)->setOrder('id', 'asc')->getFirstItem()->getData();
                break;
            case 'CustomerAddressMaster':
                $relationdata = Mage::getModel('mbizcustomeraddr/mbizcustomeraddr')->getCollection()->addFieldToFilter($fieldValue, $objectId)->setOrder('id', 'asc')->getFirstItem()->getData();
                break;
            case 'AttributeSets':
                $relationdata = Mage::getModel('mbizattributeset/mbizattributeset')->getCollection()->addFieldToFilter($fieldValue, $objectId)->setOrder('id', 'asc')->getFirstItem()->getData();
                break;
            case 'Attributes':
                $relationdata = Mage::getModel('mbizattribute/mbizattribute')->getCollection()->addFieldToFilter($fieldValue, $objectId)->setOrder('id', 'asc')->getFirstItem()->getData();
                break;
            case 'ProductCategories':
                $relationdata = Mage::getModel('mbizcategory/mbizcategory')->getCollection()->addFieldToFilter($fieldValue, $objectId)->setOrder('id', 'asc')->getFirstItem()->getData();
                break;
        }
        if (!count($relationdata)) {
            return false;
        }
        return $relationdata['magento_id'];
    }
    /*
     * function to check any record exists in mbiz status tran
     */
    public function checkMbizSyncHeaderStatus($headerId){
        $syncMbizStatusData   = Mage::getModel('syncmbizstatus/syncmbizstatus')->getCollection()->addFieldToFilter('sync_header_id', $headerId)->getFirstItem()->getData();
        return $syncMbizStatusData;
    }

    /*
     * function to create Sync record in mbiz status table on fatal error
     */
    public function createMbizSyncStatus($syncHeaderId,$syncStatus,$exception = null) {

        $syncMbizStatus = array();
        $syncMbizStatus['sync_header_id'] = $syncHeaderId;
        $syncMbizStatus['sync_status'] = $syncStatus;
        $syncMbizStatus['instance_id'] = Mage::helper('microbiz_connector')->getAppInstanceId();
        $syncMbizStatus['status_desc'] = $exception;
        $syncMbizStatusData = Mage::helper('microbiz_connector')->checkMbizSyncHeaderStatus($syncHeaderId);
        $id = $syncMbizStatusData['id'];
        ($id) ? Mage::getModel('syncmbizstatus/syncmbizstatus')->load($id)->setData($syncMbizStatus)->setId($id)->save() : Mage::getModel('syncmbizstatus/syncmbizstatus')->setData($syncMbizStatus)->save();
        //Mage::getModel('syncmbizstatus/syncmbizstatus')->setSyncHeaderId($syncHeaderId)->setData($syncMbizStatus)->save();
        return true;
    }
    /**
     * @param $category
     * @param bool $recursive
     * @return array
     * @author KT174
     * @description This method is used to find out the child categories of a given category recursive both active and inactive
     */
    public function getChildrenIds($category,$recursive=true)
    {
        //Mage::log("came to helper",null,'catsync.log');
        $categoryId = $category->getId();
        //Mage::log($categoryId,null,'catsync.log');
        $allCategories = Mage::getModel('Microbiz_Connector_Model_Category_Api')->tree($categoryId);
        $childrenIds = array();
        //Mage::log($allCategories,null,'catsync.log');
        if(!empty($allCategories))
        {
            $allChildCategories = $allCategories['children'];
            $childrenCount = $allCategories['children_count'];
            if(!empty($allChildCategories) && $childrenCount>0)
            {


                $allChildIds = Mage::helper('microbiz_connector')->getAllChildIds($allChildCategories,$childrenIds);
                //Mage::log($allChildIds,null,'catsync.log');

            }
            else {
                return $childrenIds;
            }

        }
        else {
            return $childrenIds;
        }
        return $allChildIds;
    }

    /**
     * @param $allChildCategories
     * @param array $chilrenIds
     * @return array
     * @author KT174
     * @description This Method is used to find the category ids recursively
     */
    public function getAllChildIds($allChildCategories,&$chilrenIds = array())
    {
        foreach($allChildCategories as $chilren)
        {
            $chilrenIds[] = $chilren['entity_id'];
            $childCount = $chilren['children_count'];

            if($childCount>0)
            {
                Mage::helper('microbiz_connector')->getAllChildIds($chilren['children'],$chilrenIds);
            }
        }

        return $chilrenIds;
    }
}