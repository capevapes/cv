<?php

/**
 * WDCA - Sweet Tooth
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the WDCA SWEET TOOTH POINTS AND REWARDS
 * License, which extends the Open Software License (OSL 3.0).
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
class TBT_Rewards_Model_Migration_Delete extends TBT_Rewards_Model_Migration_Abstract
{

    /**
     * Delete all Sweet Tooth rules.
     *
     * @return self
     */
    public function deleteOnlyRules()
    {
        $this->_deleteAllCatalogruleRuleData();
        $this->_deleteAllSalesruleRuleData();
        $this->_deleteSpecialRuleData();

        return $this;
    }

    /**
     * Delete all Sweet Tooth configuration settings for current admin configuration scope.
     *
     * @return self
     */
    public function deleteAllRewardsConfig()
    {
        return $this->_deleteAllConfig('rewards');
    }

    /**
     * Delete all Sweet Tooth rules && configuration settings.
     *
     * @return self
     */
    public function deleteAll()
    {
        $this->deleteOnlyRules();
        $this->deleteAllRewardsConfig();

        return $this;
    }

    /**
     * Delete all Sweet Tooth catalog rules.
     *
     * @return self
     */
    protected function _deleteAllCatalogruleRuleData()
    {
        $crs = Mage::getModel('catalogrule/rule')->getCollection()->addFieldToFilter(
            "points_action", array('neq' => '')
        );

        return $this->_deleteCollection($crs);
    }

    /**
     * Delete all Sweet Tooth cart rules.
     *
     * @return self
     */
    protected function _deleteAllSalesruleRuleData()
    {
        $srs = Mage::getModel('salesrule/rule')->getCollection()->addFieldToFilter(
            "points_action", array('neq' => '')
        );

        return $this->_deleteCollection($srs);
    }

    /**
     * Delete all Sweet Tooth special rules, including Mileston ones.
     *
     * @return self
     */
    protected function _deleteSpecialRuleData()
    {
        $srs = Mage::getModel('rewards/special')->getCollection();
        return $this->_deleteCollection($srs);
    }

    /**
     * Delete a collection of Sweet Tooth specific rules types.
     *
     * @param  Mage_Rule_Model_Mysql4_Rule_Collection  $collection Collection of rules to be deleted from the DB.
     * @param  boolean $model_key  The module key. Optional.
     * @return self
     */
    protected function _deleteCollection($collection, $model_key = false)
    {
        foreach ($collection as $sr) {
            if ($model_key) {
                $sr = Mage::getModel($model_key);
            }
            $sr->load($sr->getId())->delete();
        }

        return $this;
    }

    /**
     * Delete all Sweet Tooth configuration settings for current admin configuration scope.
     *
     * @param  string $moduleKey The module key for which to delete settings. Should preceed all settings.
     *                           Eg. 'rewards'
     * @return self
     */
    protected function _deleteAllConfig($moduleKey)
    {
        $config_table = Mage::getConfig()->getTablePrefix() . "core_config_data";
        $write        = Mage::getSingleton('core/resource')->getConnection('core_write');

        $where["path LIKE ?"] = $moduleKey . "%";
        $scopeData            = Mage::helper('rewards/migration')->getScopeData($this->getScopeParams());
        if (isset($scopeData['cond']) && isset($scopeData['value'])) {
            $where[$scopeData['cond']] = $scopeData['value'];
        }
        $select = $write->delete($config_table, $where);

        return $this;
    }

    ////////////////
    // DEPRECATED //
    ////////////////

    /**
     * @deprecated
     * @see  self::_deleteAllCatalogruleRuleData()
     */
    public function deleteAllCatalogruleRuleData()
    {
        return $this->_deleteAllCatalogruleRuleData();
    }

    /**
     * @deprecated
     * @see  self::_deleteAllSalesruleRuleData()
     */
    public function deleteAllSalesruleRuleData()
    {
        return $this->_deleteAllSalesruleRuleData();
    }

    /**
     * @deprecated
     * @see  self::_deleteSpecialRuleData()
     */
    public function deleteSpecialRuleData()
    {
        return $this->_deleteSpecialRuleData();
    }

    /**
     * @deprecated
     * @see  self::_deleteAllConfig()
     */
    public function deleteAllConfig($moduleKey)
    {
        return $this->_deleteAllConfig($moduleKey);
    }

    /**
     * @deprecated
     * @see  self::_deleteCollection
     */
    public function deleteColelction($collection, $model_key = false)
    {
        return $this->_deleteCollection($collection, $module_key);
    }
}
