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
 * This class is part of our new Smart Dispatch framework which
 * is designed to make Sweet Tooth more modular for developers
 * building on top of Sweet Tooth.
 *
 * @category   TBT
 * @package    TBT_Rewards
 * @author     Sweet Tooth Inc. <support@sweettoothrewards.com>
 */
class TBT_Rewards_Helper_Migration extends Mage_Core_Helper_Abstract
{
    protected $_dataForScope = null;

    public function getScopeData($params = null)
    {
        if (!is_null($this->_dataForScope)) {
            return $this->_dataForScope;
        }

        $description = array();
        $description['text'] = " for current configuration scope";

        // the order is important here
        $scopes = array('website', 'store');
        foreach ($scopes as $scope) {
            if (!isset($params[$scope]) || empty($params[$scope])) {
                continue;
            }

            $scopeModel = ($scope == 'store')
                ? Mage::app()->getStore($params[$scope])
                : Mage::app()->getWebsite($params[$scope]);
            $data[$scope]           = $scopeModel;
            $data['scope']          = $scope . "s";
            $data[$scope . '_name'] = $scopeModel->getName();
            $data[$scope . '_id']   = $scopeModel->getId();
            $data['cond']           = "scope = '{$data['scope']}' AND scope_id = ?";
            $data['value']          = $scopeModel->getId();
            $description['scope'][]   = $scope . " - %s";
            $description['values'][]   = $data[$scope . '_name'];
        }
        if (isset($description['scope'])) {
            $description['scope'] = " ( " . implode(' and ', $description['scope']) . " )";
        } else {
            $description['scope'] = " ( default )";
        }
        $description['text'] .= $description['scope'];

        $data['description']  = $description;
        $this->_dataForScope = $data;

        return $this->_dataForScope;
    }

    /**
     * This creates a merchant notification for import/reset of Sweet Tooth settings for the current configuration
     * scope.
     * @param  string $action The action bing performed. Options: 'imported', 'reset'
     * @param  array $params  The Magento system config configuration scope parameters
     * @return self
     */
    public function createAdminNotification($action, $params = null)
    {
        $values  = array();
        $params  = $this->getScopeData($params);
        $message = "Sweet Tooth settings were {$action} successfully";
        $values  = array();
        if (isset($params['description']) && !empty($params['description'])) {
            $message .= $params['description']['text'];
            $values   = isset($params['description']['values']) ? $params['description']['values'] : $values;
        }
        $message .= ".";
        array_unshift($values, $message);
        $message = call_user_func_array(array(Mage::helper('adminhtml'), '__'), $values );

        Mage::getSingleton('adminhtml/session')->addSuccess($message);

        return $this;
    }
}
