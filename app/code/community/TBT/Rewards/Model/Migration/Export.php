<?php

/**
 * WDCA - Sweet Tooth
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the WDCA SWEET TOOTH POINTS AND REWARDS
 * License, which extends the Open Software License (OSL 3.0).
 * The Sweet Tooth License is available at this URL:
 * https://www.sweettoothrewards.com/terms-of-service
 * The Open Software License is available at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 *
 * By adding to, editing, or in any way modifying this code, WDCA is
 * not held liable for any inconsistencies or abnormalities in the
 * behaviour of this code.
 * By adding to, editing, or in any way modifying this code, the Licensee
 * terminates any agreement of support offered by WDCA, outlined in the
 * provided Sweet Tooth License.
 * Upon discovery of modified code in the process of support, the Licensee
 * is still held accountable for any and all billable time WDCA spent
 * during the support process.
 * WDCA does not guarantee compatibility with any other framework extension.
 * WDCA is not responsbile for any inconsistencies or abnormalities in the
 * behaviour of this code if caused by other framework extension.
 * If you did not receive a copy of the license, please send an email to
 * support@sweettoothrewards.com or call 1.855.699.9322, so we can send you a copy
 * immediately.
 *
 * @category   [TBT]
 * @package    [TBT_Rewards]
 * @copyright  Copyright (c) 2014 Sweet Tooth Inc. (http://www.sweettoothrewards.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Sales Rule Rule
 *
 * @category   TBT
 * @package    TBT_Rewards
 * @author     Sweet Tooth Inc. <support@sweettoothrewards.com>
 */
class TBT_Rewards_Model_Migration_Export extends TBT_Rewards_Model_Migration_Abstract
{
    const DATA_CATALOGRULE_RULE = 'catalogrule_rule';
    const DATA_SALESRULE_RULE   = 'salesrule_rule';
    const DATA_SPECIAL_RULE     = 'special_rule';
    const DATA_CURRENCY         = 'currency';
    const DATA_CONFIG           = 'config';
    const EXT                   = 'stcampaign';

    /**
     * Retrieves all Sweet Tooth rules serialized, ready for export.
     *
     * @return string
     */
    public function getSerializedCampaignExport()
    {
        $output                              = array();
        $output[self::DATA_CATALOGRULE_RULE] = $this->_getAllCatalogruleRuleData();
        $output[self::DATA_SALESRULE_RULE]   = $this->_getAllSalesruleRuleData();
        $output[self::DATA_SPECIAL_RULE]     = $this->_getSpecialRuleData();
        $output[self::DATA_CURRENCY]         = $this->_getCurrencyData();
        $soutput                             = serialize($output);

        return $soutput;
    }

    /**
     * Retrieves all Sweet Tooth configuration settings serialized, ready for export.
     *
     * @return string
     */
    public function getSerializedConfigExport()
    {
        $output                    = array();
        $output[self::DATA_CONFIG] = $this->_getRewardsConfigData();
        $soutput                   = serialize($output);

        return $soutput;
    }

    /**
     * Retrieves all Sweet Tooth rules & settings serialized, ready for export.
     *
     * @return string
     */
    public function getSerializedFullExport()
    {
        $output                              = array();
        $output[self::DATA_CATALOGRULE_RULE] = $this->_getAllCatalogruleRuleData();
        $output[self::DATA_SALESRULE_RULE]   = $this->_getAllSalesruleRuleData();
        $output[self::DATA_SPECIAL_RULE]     = $this->_getSpecialRuleData();
        $output[self::DATA_CURRENCY]         = $this->_getCurrencyData();
        $output[self::DATA_CONFIG]           = $this->_getRewardsConfigData();
        $soutput                             = serialize($output);

        return $soutput;
    }

    /**
     * Retrieves an array containing all Sweet Tooth catalog rules.
     *
     * @return array
     */
    protected function _getAllCatalogruleRuleData()
    {
        $crs = Mage::getModel('catalogrule/rule')->getCollection()
            ->addFieldToFilter("points_action", array('neq' => ''));

        if (Mage::helper('rewards/version')->isBaseMageVersionAtLeast('1.7.0.0.')) {
            // starting with Magento 1.7 websitesIds are not in the rules table anymore
            $crs->addWebsitesToResult();
        }

        return $this->_getCleanArray($crs);
    }

    /**
     * Retrieves an array containing all Sweet Tooth cart rules.
     *
     * @return array
     */
    protected function _getAllSalesruleRuleData()
    {
        $srs = Mage::getModel('salesrule/rule')->getCollection()
            ->addFieldToFilter("points_action", array('neq' => ''));

        if (Mage::helper('rewards/version')->isBaseMageVersionAtLeast('1.7.0.0.')) {
            // starting with Magento 1.7 websitesIds are not in the rules table anymore
            $srs->addWebsitesToResult();
        }

        return $this->_getCleanArray($srs);
    }

    /**
     * Retrieves an array containing all Sweet Tooth special rules (customer behavior rules).
     *
     * @return array
     */
    protected function _getSpecialRuleData()
    {
        $specialRules = Mage::getModel('rewards/special')->getCollection()
            ->addMilestoneRules();

        return $this->_getCleanArray($specialRules);
    }

    /**
     * Retrieves an array containing all Sweet Tooth configuration settings.
     *
     * @return array
     */
    protected function _getRewardsConfigData()
    {
        return $this->_getConfigData('rewards');
    }

    /**
     * Retrieves a collection of configurations (config_data entries) for the module specified. Based on the current
     * scope configuration params passed it will filter base on website or store.
     *
     * @param  string $moduleKey    The module key that preceedes all configuration settings.
     * @return array                Collection of configs.
     */
    protected function _getConfigData($moduleKey)
    {
        $config_table = Mage::getConfig()->getTablePrefix() . "core_config_data";
        $read         = Mage::getSingleton('core/resource')->getConnection('core_read');

        $where["path LIKE ?"]      = $moduleKey . "%";
        $scopeData                 = Mage::helper('rewards/migration')->getScopeData($this->getScopeParams());
        if (isset($scopeData['cond']) && isset($scopeData['value'])) {
            $where[$scopeData['cond']] = $scopeData['value'];
        }

        $select = $read->select()->from($config_table);
        foreach ($where as $cond => $value) {
            $select->where($cond, $value);
        }
        $collection = $read->fetchAll($select);

        return $collection;
    }

    /**
     * Retrieves an array containing all Sweet Tooth currency data, ready for export.
     *
     * @return array
     */
    protected function _getCurrencyData()
    {
        $srs = Mage::getSingleton('rewards/currency')->getCollection();
        return $this->_getCleanArray($srs);
    }

    /**
     * Processes a collection of Sweet Tooth rules and returns an array with all the data.
     *
     * @param  Mage_Rule_Model_Mysql4_Rule_Collection $collection Collection of Sweet Tooth rules.
     * @return array
     */
    protected function _getCleanArray($collection)
    {
        $output = array();
        foreach ($collection as &$sr) {
            if ($sr instanceof Mage_SalesRule_Model_Rule) {
                $sr->getStoreLabels();
            }
            $output[] = $sr->getData();
        }

        return $output;
    }

    ////////////////
    // DEPRECATED //
    ////////////////

    /**
     * @deprecated
     * @see  self::_getAllCatalogRuleData()
     */
    public function getAllCatalogruleRuleData()
    {
        return $this->_getAllCatalogRuleData();
    }

    /**
     * @deprecated
     * @see  self::_getAllSalesruleRuleData()
     */
    public function getAllSalesruleRuleData()
    {
        return $this->_getAllSalesruleRuleData();
    }

    /**
     * @deprecated
     * @see  self::_getSpecialRuleData()
     */
    public function getSpecialRuleData()
    {
        return $this->_getSpecialRuleData();
    }

    /**
     * @deprecated
     * @see  self::_getRewardsConfigData()
     */
    public function getRewardsConfigData()
    {
        return $this->_getRewardsConfigData();
    }

    /**
     * @deprecated
     * @see  self::_getConfigData()
     */
    public function getConfigData()
    {
        return $this->_getConfigData();
    }

    /**
     * @deprecated
     * @see  self::_getCurrencyData()
     */
    public function getCurrencyData()
    {
        return $this->_getCurrencyData();
    }
}
