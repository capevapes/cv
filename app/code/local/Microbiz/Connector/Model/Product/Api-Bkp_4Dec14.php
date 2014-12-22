<?php
//Version 113
class Microbiz_Connector_Model_Product_Api extends Mage_Catalog_Model_Product_Api
{
    /**
     * Retrieve list of products with partial info (id, sku, type, set, name, price, ...)
     *
     * @param array $filters
     * @param string|int $store
     * @return array
     */
    public function listPartial($filters = null, $store = null, $exclude = false,$pageNumber = null,$pageSize = null,$importAll = false)
    {
        /* Mage::Log('list Partial');
        Mage::Log($filters);
        Mage::Log($store);
        Mage::Log($exclude);
        Mage::Log($pageNumber);
        Mage::Log($pageSize);     */
        $collection = Mage::getModel('catalog/product')->getCollection()->setStoreId($this->_getStoreId($store))->setFlag('require_stock_items', true)->addAttributeToSelect('name')->addAttributeToSelect('price')->addAttributeToSelect('status');
        if($exclude) {
            $existsCollection = Mage::getModel('mbizproduct/mbizproduct')->getCollection()->addFieldToSelect('magento_id')->getData();
            foreach($existsCollection as $relationPrd) {
                $existsIds[] = $relationPrd['magento_id'];
            }
        }
        Mage::log("exclude is".$exclude,null,'productcount.log');
        if (is_array($filters)) {
            try {
                foreach ($filters as $field => $value) {
                    if($field=='category_id') {
                        $collection->joinField('category_id',
                            'catalog/category_product',
                            'category_id',
                            'product_id=entity_id',
                            null,
                            'left');

                    }

                    else {
                        if (isset($this->_filtersMap[$field])) {
                            $field = $this->_filtersMap[$field];
                        }
                    }
                    if ($field == 'created_at' || $field == 'updated_at') {
                        $attributeFilter[] = array(
                            'attribute' => $field,
                            'from' => $value
                        );
                    }
                    else if($field=='entity_id')
                    {
                        if($exclude) {
                            $value['in'] = array_diff($value['in'], $existsIds);
                        }
                        $collection->addFieldToFilter($field, $value)->distinct(true);
                    }
                    else
                        $collection->addFieldToFilter($field, $value);
                }
                $collection->addFieldToFilter($attributeFilter)->distinct(true);;
                //$collection->addFieldToFilter('type_id', array( '=' => 'simple' ));
            }
            catch (Mage_Core_Exception $e) {
                $this->_fault('filters_invalid', $e->getMessage());
            }
        }
        if($exclude) {
            Mage::log("came to exclude filtering part",null,'productcount.log');
            $collection->addFieldToFilter('entity_id', array('nin'=>$existsIds));
        }
        $totalPages = ceil($this->productsCount($filters,$store, $exclude) / $pageSize) ;
        if($pageNumber) {
            $collection->getSelect()->limit($pageSize,$pageSize * ($pageNumber-1));
        }
        /*  if($pageSize) {
              $collection->getSelect()->setPageSize($pageSize);
          } */
//$collection->distinct(true);
        $collection->getSelect()->group('e.entity_id')->distinct(true);
        $collection->load();
        //return count($collection);
        //      $collection->groupby(array('entity_id'));
        Mage::log((string)$collection->getSelect(),null,'productcount.log',true);
        $result = array();
        $arrTemp = array();
        if($importAll) {
            foreach ($collection as $product) {
                $result[] = $product->getId();

            }
        } else {
        foreach ($collection as $product) {

            $arrProduct = array( // Basic product data
                // 'id' =>  "product_".$product->getId(),
                'magentoId' => $product->getId(),
                'product_id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'set' => $product->getAttributeSetId(),
                'attribute_set_name' => Mage::getModel('eav/entity_attribute_set')->load($product->getAttributeSetId())->getAttributeSetName(),
                'mageItem' => $product->getName(),
                'mbizItem' => $product->getName(),
                'iconCls' => 'task',
                'leaf' => true,
                // 'type' => 'attribute',
                'type' => $product->getTypeId(),
                'sys_required' => 0,
                // 'status' => $product->getStatus(),
                'qty' => $product->getStockItem()->data
            );

            $result[] = $arrProduct;

        }
        }
//      Mage::log($arrTemp,null,'productcount.log');
        $totalResult=array('totalPages'=>$totalPages,'products'=>$result);
        Mage::Log(count($result));
        return $totalResult;
    }

    /**
     * Retrieve list of products with FULL info (id, sku, type, set, name, price, ...).
     * VERY SLOW!
     *
     * @param array $filters
     * @param string|int $store
     * @return array
     */
    public function listFull($filters = null, $store = null)
    {
        $collection = Mage::getModel('catalog/product')->getCollection()->setStoreId($this->_getStoreId($store))->addAttributeToSelect('*');
        
        $result = array();
        
        if (is_array($filters)) {
            try {
                foreach ($filters as $field => $value) {
                    if (isset($this->_filtersMap[$field])) {
                        $field = $this->_filtersMap[$field];
                    }
                    if ($field == 'created_at' || $field == 'updated_at')
                        $attributeFilter[] = array(
                            'attribute' => $field,
                            'from' => $value
                        );
                    else
                        $collection->addFieldToFilter($field, $value);
                }
                $collection->addFieldToFilter($attributeFilter);
                //$collection->addFieldToFilter('type_id', array( '=' => 'simple' ));
            }
            catch (Mage_Core_Exception $e) {
                $this->_fault('filters_invalid', $e->getMessage());
            }
        }
        
        $result = array();
        
        foreach ($collection as $product) {
            $arrProduct = array( // Basic product data
                'product_id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'set' => $product->getAttributeSetId(),
                'type' => $product->getTypeId(),
                'categories' => $product->getCategoryIds(),
                'websites' => $product->getWebsiteIds(),
                'qty' => ($product->getStockItem()) ? $product->getStockItem()->getQty() : '',
                'is_in_stock' => ($product->getStockItem()) ? $product->getStockItem()->getIsInStock() : ''
            );
            
            $arrProduct['attributes'] = $product->getData();
            $result[]                 = $arrProduct;
        }
        
        return $result;
    }
    
    /**
     * Retrieve product info
     *
     * @param int|string $productId
     * @param string|int $store
     * @param array $attributes
     * @return array
     */
    public function infoFull($productIds, $store = null, $attributes = null)
    {
        if (!is_array($productIds)) {
            $productIds = array(
                $productIds
            );
        }

        $product = Mage::getModel('catalog/product');

        $collection = Mage::getModel('catalog/product')->getCollection()->setStoreId($this->_getStoreId($store))->setFlag('require_stock_items', true)->addFieldToFilter('entity_id', array(
            'in' => $productIds
        ))->addAttributeToSelect('*');

        $result = array();

        foreach ($collection as $product) {

            $result = array( // Basic product data
                'product_id' => $product->getId(),
                'sku' => $product->getSku(),
                'set' => $product->getAttributeSetId(),
                'type' => $product->getTypeId(),
                'categories' => $product->getCategoryIds(),
                'websites' => $product->getWebsiteIds(),
                'qty' => ($product->getStockItem()) ? (int) $product->getStockItem()->getQty() : '',
                'is_in_stock' => ($product->getStockItem()) ? $product->getStockItem()->getIsInStock(): '',
                'media' => Mage::getModel('Mage_Catalog_Model_Product_Attribute_Media_Api')->items($product->getId())
            );

            foreach ($product->getTypeInstance()->getEditableAttributes() as $attribute) {
                if ($this->_isAllowedAttribute($attribute, $attributes)) {
                    $result[$attribute->getAttributeCode()] = $product->getData($attribute->getAttributeCode());
                }
            }
            $parentIds = Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
            if($parentIds) {
                $result['associated_configurable_products'] = $parentIds;
            }
            if($product->getTypeId() == "configurable"){
                $confAttributes = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);
                $result['configurable_attributes_data'] = $confAttributes;
                $associatedProducts = $product->getTypeInstance()->getUsedProducts();
                $configcount=0;
                $associatedProductIds = array();
                foreach($associatedProducts as $associatedProduct) {
                    $associatedProductIds[$configcount]=$associatedProduct->getId();
                    $configcount++;
                }
                $result['associated_simple_products']=$associatedProductIds;
            }
            if($product->getTypeId() == "simple"){
                $childPrdParentIds = Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
                $result['associated_configurable_products']=$childPrdParentIds;
            }
            $tax_helper = Mage::getSingleton('tax/calculation');
            $tax_request = $tax_helper->getRateOriginRequest();
            $tax_request->setProductClassId($product->getTaxClassId());

            $tax = $tax_helper->getRate($tax_request);
            $calculator = Mage::getSingleton('tax/calculation');
            if(!$result['store_price']) {
                if(Mage::getStoreConfig('tax/calculation/price_includes_tax')) {
                    $price_excluding_tax=Mage::helper('tax')->getPrice($product, $product->getPrice(),0);
                    $tax_amount = $calculator->calcTaxAmount($product->getPrice(), $tax, true, true);
                    $result['store_price'] = $price_excluding_tax;
                    $result['tax_amount'] = $tax_amount;
                }
                else {
                    $result['store_price'] = $product->getPrice();
                }
            }
            else {
                if(Mage::getStoreConfig('tax/calculation/price_includes_tax')) {
                    $price_excluding_tax=Mage::helper('tax')->getPrice($product, $product->getStorePrice(),0);
                    $tax_amount = $calculator->calcTaxAmount($product->getStorePrice(), $tax, true, true);
                    $result['store_price'] = $price_excluding_tax;
                    $result['tax_amount'] = $tax_amount;
                }
                else {
                    $result['store_price'] = $product->getStorePrice();
                }
            }


            $result['tax_rate'] = $tax;
            // KT097 Code for Store price excluding tax End
            $finalresult[] = $result;
        }

        return $finalresult;
    }
	

	public function create($type, $set, $sku, $productData, $store = null)
    {
        $errors =array();
        (!$type) ? $errors[] = 'Please Specify Product type'  : null;
        (!$set) ? $errors[] = 'Please Specify Product Attribute Set' : null;
        (!$sku) ? $errors[] = 'Please Provide SKU' : null;
        if(count($errors)){
            $this->_fault('data_invalid',implode("\n",$errors));
        }
        if (!in_array($type, array_keys(Mage::getModel('catalog/product_type')->getOptionArray()))) {
            $this->_fault('product_type_not_exists');
        }
       $attributeSet = Mage::getModel('eav/entity_attribute_set')->load($set);
        if (is_null($attributeSet->getId())) {
            $this->_fault('product_attribute_set_not_exists');
        }
        if (Mage::getModel('catalog/product')->getResource()->getTypeId() != $attributeSet->getEntityTypeId()) {
            $this->_fault('product_attribute_set_not_valid');
        }

        /** @var $product Mage_Catalog_Model_Product */
        $product = Mage::getModel('catalog/product');
        $product->setStoreId($this->_getStoreId($store))
            ->setAttributeSetId($set)
            ->setTypeId($type)
            ->setSku($sku);

        if (!isset($productData['stock_data']) || !is_array($productData['stock_data'])) {
            //Set default stock_data if not exist in product data
            // For setting Congigurable Manage Stock Based on configuration
            if($type == 'configurable'){
                $manageStockData = array();
                $manageStock=Mage::getStoreConfig('connector/configproduct/managestock');
                ($manageStock == 2) ? $manageStockData['use_config_manage_stock'] = 1 : $manageStockData['manage_stock'] = $manageStock;
                $product->setStockData(array($manageStockData));
            }
            else {
            $product->setStockData(array('use_config_manage_stock' => 1));
        }
        }

        foreach ($product->getMediaAttributes() as $mediaAttribute) {
            $mediaAttrCode = $mediaAttribute->getAttributeCode();
            $product->setData($mediaAttrCode, 'no_selection');
        }

       $this->_prepareDataForSave($product, $productData);

        try {
            /**
             * @todo implement full validation process with errors returning which are ignoring now
             * @todo see Mage_Catalog_Model_Product::validate()
             */
            if (is_array($errors = $product->validate())) {
                $strErrors = array();
                foreach($errors as $code => $error) {
                    if ($error === true) {
                        $error = Mage::helper('catalog')->__('Attribute "%s" is invalid.', $code);
                    }
                    $strErrors[] = $error;
                }
                $this->_fault('data_invalid', implode("\n", $strErrors));
            }

            $product->save();
        } catch (Mage_Eav_Model_Entity_Attribute_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }
        catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }
        catch (Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        return $product->getId();
    }
	
	/**
     * Update product data
     * 
     * @param int|string $productId
     * @param array $productData
     * @param string|int $store
     * @return boolean
     */
    public function update($productId, $productData, $store = null, $identifierType = null)
    {
        $product = parent::_getProduct($productId, $store, $identifierType);
        /*Add Simple Variants to Configurable product Code Starts Here.*/
        $attributeInfo = $productData['attribute_info'];
        if(!empty($attributeInfo)) {
            $config_product = Mage::getModel('catalog/product')->load($productId);
            $productAttributeOptions = $config_product->getTypeInstance(true)->getConfigurableAttributesAsArray($config_product);
            //print_r($productAttributeOptions);
            $newAttrIds = array();
            foreach($attributeInfo as $attribute)
            {
                $attrId = $attribute['attribute'];
                $isAttrExists =0;
                if(!empty($productAttributeOptions))
                {
                    foreach($productAttributeOptions as $productAttribute)
                    {
                        $prdAttrId = $productAttribute['attribute_id'];
                        if($attrId==$prdAttrId)
                        {
                            $isAttrExists=1;
                        }
                    }

                }
                if(!$isAttrExists)
                {
                    $newAttrIds[] = $attrId;

                }

            }

            if(!empty($newAttrIds))
            {

                $config_product->setCanSaveConfigurableAttributes(true);
                $config_product->setCanSaveCustomOptions(true);
                $cProductTypeInstance = $config_product->getTypeInstance();

                $attribute_ids = array($newAttrIds);
                $cProductTypeInstance->setUsedProductAttributeIds($attribute_ids);
                $attributes_array = $cProductTypeInstance->getConfigurableAttributesAsArray();

                foreach($attributes_array as $key=>$attribute_array)
                {
                    $attributes_array[$key]['use_default'] = 1;
                    $attributes_array[$key]['position'] = 0;

                    if (isset($attribute_array['frontend_label']))
                    {
                        $attributes_array[$key]['label'] = $attribute_array['frontend_label'];
                    }
                    else {
                        $attributes_array[$key]['label'] = $attribute_array['attribute_code'];
                    }
                }
                //print_r($attributes_array);
                $config_product->setConfigurableAttributesData($attributes_array);
                $config_product->save();
                //echo "attribute assigned successfully";
            }
        }
        /*Add Simple Variants to Configurable product Code Ends Here.*/
		if(isset($productData['attribute_info'])) {
			unset($productData['attribute_info']);
		}
        $this->_prepareDataForSave($product, $productData);

        try {

            if (is_array($errors = $product->validate())) {
                $strErrors = array();
                foreach($errors as $code => $error) {
                    if ($error === true) {
                        $error = Mage::helper('catalog')->__('Value for "%s" is invalid.', $code);
                    } else {
                        $error = Mage::helper('catalog')->__('Value for "%s" is invalid: %s', $code, $error);
                    }
                    $strErrors[] = $error;
                }
                $this->_fault('data_invalid', implode("\n", $strErrors));
            }
            /*$resource = Mage::getSingleton('core/resource');
            //get an object with access to direct queries
            $connection = $resource->getConnection('core_write');

            $sql = "DELETE FROM {$resource->getTableName('catalog_product_super_attribute')}
            WHERE  product_id = {$product->getId()}";
            $connection->query($sql);*/

            $product->save();
        } catch (Mage_Eav_Model_Entity_Attribute_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }
        catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }
        catch (Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        return true;
    }
	/**
     *  Set additional data before product saved
     *
     *  @param    Mage_Catalog_Model_Product $product
     *  @param    array $productData
     *  @return	  object
     */
	protected function _prepareDataForSave($product, $productData)
    {
        
		if (isset($productData['website_ids']) && is_array($productData['website_ids'])) {
            $product->setWebsiteIds($productData['website_ids']);
        }

        foreach ($product->getTypeInstance(true)->getEditableAttributes($product) as $attribute) {
            //Unset data if object attribute has no value in current store
            if (Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID !== (int) $product->getStoreId()
                && !$product->getExistsStoreValueFlag($attribute->getAttributeCode())
                && !$attribute->isScopeGlobal()
            ) {
                $product->setData($attribute->getAttributeCode(), false);
            }

            if ($this->_isAllowedAttribute($attribute)) {
                if (isset($productData[$attribute->getAttributeCode()])) {
                    $product->setData(
                        $attribute->getAttributeCode(),
                        $productData[$attribute->getAttributeCode()]
                    );
                } elseif (isset($productData['additional_attributes']['single_data'][$attribute->getAttributeCode()])) {
                    $product->setData(
                        $attribute->getAttributeCode(),
                        $productData['additional_attributes']['single_data'][$attribute->getAttributeCode()]
                    );
                } elseif (isset($productData['additional_attributes']['multi_data'][$attribute->getAttributeCode()])) {
                    $product->setData(
                        $attribute->getAttributeCode(),
                        $productData['additional_attributes']['multi_data'][$attribute->getAttributeCode()]
                    );
                }
            }
        }

        if (isset($productData['categories']) && is_array($productData['categories'])) {
            $product->setCategoryIds($productData['categories']);
        }

        if (isset($productData['websites']) && is_array($productData['websites'])) {
            foreach ($productData['websites'] as &$website) {
                if (is_string($website)) {
                    try {
                        $website = Mage::app()->getWebsite($website)->getId();
                    } catch (Exception $e) { }
                }
            }
            $product->setWebsiteIds($productData['websites']);
        }

        if (Mage::app()->isSingleStoreMode()) {
            $product->setWebsiteIds(array(Mage::app()->getStore(true)->getWebsite()->getId()));
        }

        if (isset($productData['stock_data']) && is_array($productData['stock_data'])) {
            $product->setStockData($productData['stock_data']);
        }

        if (isset($productData['tier_price']) && is_array($productData['tier_price'])) {
             $tierPrices = Mage::getModel('catalog/product_attribute_tierprice_api')
                 ->prepareTierPrices($product, $productData['tier_price']);
             $product->setData(Mage_Catalog_Model_Product_Attribute_Tierprice_Api::ATTRIBUTE_CODE, $tierPrices);
        }

        /*
         * Check for configurable products array passed through API Call
        */
        if(isset($productData['configurable_products_data']) && is_array($productData['configurable_products_data'])) {
		    $product->setConfigurableProductsData($productData['configurable_products_data']);
        }
          
        if(isset($productData['attribute_info']) && is_array($productData['attribute_info'])) {
			$configAttributes=$productData['attribute_info'];
						$i=0;
						$requiredFormat=array();
						foreach($configAttributes as $configAttribute)	 {
							$eavAttribute = new Mage_Eav_Model_Mysql4_Entity_Attribute();
							//$code = $eavAttribute->getIdByCode('catalog_product', $configAttribute);
							//$configArray= Mage::getModel('eav/entity_attribute')->load($code)->getData();
                            $magentoAttributeId = $configAttribute['attribute'];
                            if(!$magentoAttributeId) {
                                $magentoAttributeId = Mage::helper('microbiz_connector')->getObjectRelation($configAttribute['mbiz_attribute_id'], 'Attributes','Mbiz');
                            }
							$configArray= Mage::getModel('eav/entity_attribute')->load($magentoAttributeId)->getData();
							$requiredFormat[$i]=array (
							  'id' => NULL,
							  'label' => $configArray['frontend_label'],
							  'use_default' => NULL,
							  'position' => NULL,
							  'values' => array (),
							  'attribute_id' => $configArray['attribute_id'],
							  'attribute_code' => $configArray['attribute_code'],
							  'frontend_label' => $configArray['frontend_label'],
							  'store_label' => $configArray['frontend_label'],
							  'html_id' => 'configurable__attribute_'.$i,
							);
							$i++;
						}
						$productData['configurable_attributes_data']=$requiredFormat;
		}
		if(isset($productData['configurable_attributes_data']) && is_array($productData['configurable_attributes_data'])) {

            foreach($productData['configurable_attributes_data'] as $key => $data) {
                //Check to see if these values exist, otherwise try and populate from existing values
               // $data['label']          =   (!empty($data['label']))            ? $data['label']            : $product->getResource()->getAttribute($data['attribute_code'])->getStoreLabel();
                $data['label']          =   (!empty($data['label']))            ? $data['label']            : Mage::getModel('eav/config')->getAttribute('catalog_product', $data['attribute_code'])->getStoreLabel();
                $data['frontend_label'] =   (!empty($data['frontend_label']))   ? $data['frontend_label']   : Mage::getModel('eav/config')->getAttribute('catalog_product', $data['attribute_code'])->getFrontendLabel();
               // $data['frontend_label'] =   (!empty($data['frontend_label']))   ? $data['frontend_label']   : $product->getResource()->getAttribute($data['attribute_code'])->getFrontendLabel();

                $productData['configurable_attributes_data'][$key] = $data;
            }
            $product->setConfigurableAttributesData($productData['configurable_attributes_data']);
            $product->setCanSaveConfigurableAttributes(1);
        }
    }
    public function productCreate($type, $set, $sku, $productData, $store = null)
    {  
        // Mage::log($productData['storeinventory']);
		try{
        $productId = parent::create($type, $set, $sku, $productData, $store);
        
        foreach ($productData['storeinventory'] as &$inventoryData) {
            $inventoryData['material_id'] = $productId;
        }
		Mage::getModel('Microbiz_Connector_Model_Storeinventory_Api')->createMbizInventory($productData['storeinventory'], $productId);
		}
         catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }
        return $productId;
    }
    
    public function productUpdate($productId, $productData, $store = null, $identifierType = null)
    {
        $product = $this->_getProduct($productId, $store, $identifierType);
		// $this->_fault('data_invalid', $productId.' '. $identifierType);
        try {
            parent::update($productId, $productData);
			foreach ($productData['storeinventory'] as &$inventoryData){
                $inventoryData['material_id'] = $product->getId();
            }
			Mage::getModel('Microbiz_Connector_Model_Storeinventory_Api')->createMbizInventory($productData['storeinventory'], $product->getId());
        }
        catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }
        return true;
    }

    /**
     * Retrieve count of products
     *
     * @param array $filters
     * @param string|int $store
     * @return count
     */
    public function productsCount($filters = null, $store = null, $exclude = false)
    {
        $collection = Mage::getModel('catalog/product')->getCollection()->setStoreId($this->_getStoreId($store))->setFlag('require_stock_items', true)->addAttributeToSelect('*');
        Mage::log($exclude,null,'productcount.log');
        if($exclude) {
            $existsCollection = Mage::getModel('mbizproduct/mbizproduct')->getCollection()->addFieldToSelect('magento_id')->getData();
            foreach($existsCollection as $relationPrd) {
                $existsIds[] = $relationPrd['magento_id'];
            }
        }
//return $existsIds;
        Mage::log($existsIds,null,'productcount.log');
        Mage::log($filters,null,'productcount.log');
        Mage::log("filters over",null,'productcount.log');
        if (is_array($filters) && count($filters)) {
            try {
                foreach ($filters as $field => $value) {
                    if($field=='category_id') {
                        $collection->joinField('category_id',
                            'catalog/category_product',
                            'category_id',
                            'product_id=entity_id',
                            null,
                            'left');
                    }
                    else {
                        if (isset($this->_filtersMap[$field])) {
                            $field = $this->_filtersMap[$field];
                        }
                    }
                    if ($field == 'created_at' || $field == 'updated_at') {
                        $attributeFilter[] = array(
                            'attribute' => $field,
                            'from' => $value
                        );
                    }
                    else if($field=='entity_id')
                    {
                        if($exclude) {
                            $value['in'] = array_diff($value['in'], $existsIds);
                        }
                        $collection->addFieldToFilter($field, $value);
                    }
                    else
                        $collection->addFieldToFilter($field, $value);
                }
                $collection->addFieldToFilter($attributeFilter);
                //$collection->addFieldToFilter('type_id', array( '=' => 'simple' ));
            }
            catch (Mage_Core_Exception $e) {
                $this->_fault('filters_invalid', $e->getMessage());
            }
        }
        // else {
        Mage::log("Came to Else Part",null,'productcount.log');
        if($exclude) {
            $collection->addFieldToFilter('entity_id', array('nin'=>$existsIds));
        }
        // }
//$collection->getSelect()->distinct(true);
        $collection->getSelect()->group('e.entity_id')->distinct(true);
        $collection->load();
        return $collection->count();
    }
}

?>
