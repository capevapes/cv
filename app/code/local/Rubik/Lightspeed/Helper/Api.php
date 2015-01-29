<?php

class Rubik_Lightspeed_Helper_Api extends Mage_Core_Helper_Abstract
{
    protected $apiUrl = 'https://api.merchantos.com/API/';
    protected $apiKey;
    protected $accountNum;

    public function __construct()
    {
        $this->apiKey = '3fee8e5840a7f996fc60a29c7f22ae202abc27da6e55f7ddad04641a00b5e7c6';
        $this->accountNum = '100482';
    }

    public function makeAPICall($controlName, $action, $uniqueId = null, $xml = null, $emitter = "xml", $queryStr = false)
    {
        $customRequest = 'GET';
        switch ($action) {
            case 'Create':
                $customRequest = 'POST';
                break;
            case 'Read':
                $customRequest = 'GET';
                break;
            case 'Update':
                $customRequest = 'PUT';
                break;
            case 'Delete':
                $customRequest = 'DELETE';
                break;
        }
        $curl = Mage::helper('lightspeed/curl');
        $curl->setBasicAuth($this->apiKey,'apikey');
        $curl->setVerifyPeer(false);
        $curl->setVerifyHost(0);
        $curl->setCustomRequest($customRequest);

        $controlUrl = $this->apiUrl . str_replace('.', '/', str_replace('Account.', 'Account.' . $this->accountNum . '.', $controlName));
        if (isset($uniqueId)) {
            $controlUrl .= '/' . $uniqueId;
        }

        if ($queryStr) {
            $controlUrl .= '.' . $emitter . '?' . $queryStr;
        } else {
            $controlUrl .= '.' . $emitter;
        }

        if (is_object($xml)) {
            $xml = $xml->asXML();
        }

        return self::_makeCall($curl, $controlUrl, $xml);
    }

    protected static function _makeCall($curl, $url, $xml)
    {
        $result = $curl->call($url, $xml);

        try {
            $resultXml = new SimpleXMLElement($result);
        } catch (Exception $e) {
            throw new Exception("MerchantOS API Call Error: " . $e->getMessage() . ", Response: " . $result);
        }

        if (!is_object($resultXml)) {
            throw new Exception("MerchantOS API Call Error: Could not parse XML, Response: " . $result);
        }

        return $resultXml;
    }
}
