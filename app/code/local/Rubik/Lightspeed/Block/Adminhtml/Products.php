<?php

class Rubik_Lightspeed_Block_Adminhtml_Products extends Mage_Core_Block_Template
{
    public function __construct()
    {
        $this->setTemplate('rubik/products.phtml');
        parent::__construct();
    }

    public function getProducts()
    {
        $api = Mage::helper('lightspeed/api');
        $inventoryXml = $api->makeAPICall('Account.Inventory', 'Read');

        $products = array();
        $productIds = array();

        foreach ($inventoryXml->Inventory as $inventory) {
            $productIds[] = intval($inventory->itemID);
        }

        $productIds = array_unique($productIds);
        foreach ($productIds as $productId) {
            $products[] = $this->getProductInfo($productId);
        }

        return $products;
    }

    public function getProductInfo($productId)
    {
        $api = Mage::helper('lightspeed/api');
        $productXml = $api->makeAPICall('Account.Item', 'Read', $productId);

        $sku = strval($productXml->customSku);

        $productsInfo = array();
        $productsInfo['sku'] = $sku;
        $productsInfo['exists'] = 2;

        if (Mage::getModel('catalog/product')->getIdBySku($sku)) {
            $productsInfo['exists'] = 1;
        }

        return $productsInfo;
    }
}
