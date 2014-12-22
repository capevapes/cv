<?php

/**
 * WDCA - Sweet Tooth
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the WDCA SWEET TOOTH POINTS AND REWARDS
 * License, which extends the Open Software License (OSL 3.0).
 *
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

class TBT_Rewards_Model_Birthday_Transfer extends TBT_Rewards_Model_Transfer
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get all birthday transfers
     *
     * @return TBT_Rewards_Model_Mysql4_Transfer_Collection
     */
    public function getTransfersAssociatedWithBirthday()
    {
        $transferCollection = Mage::getModel('rewards/birthday_transfer')->getCollection();
        $transferCollection->addFilter('reason_id', TBT_Rewards_Model_Birthday_Reason::REASON_TYPE_ID);

        return $transferCollection;
    }

    /**
     * Get most recent birthday transfer linked to a provided customer
     * Returns null if no fransfer is found
     *
     * @param type $customer
     * @return type
     */
    public function getMostRecentBirthdayTransfer($customer)
    {
        // latest birthday transfer
        $birthdayTransfers = $this->getTransfersAssociatedWithBirthday()
            ->addFilter('customer_id', $customer->getId())->load();

        $latest_transfer = null;
        foreach ($birthdayTransfers as $transfer) {
            if (null == $latest_transfer) {
                $latest_transfer = $transfer;
            }
            if (strtotime($transfer->getCreationTs()) >= strtotime($latest_transfer->getCreationTs())) {
                $latest_transfer = $transfer;
            }
        }

        return $latest_transfer;
    }

    public function getTransferComment()
    {
        return $this->_getHelper()->getTransferComment();
    }

    public function getInitialTransferStatus()
    {
        return $this->_getHelper()->getInitialTransferStatus();
    }

    /**
     * Do the points transfer for the review
     *
     * @param  TBT_Rewards_Model_Customer $customer
     * @param  int $rule Special Rule
     * @return boolean whether or not the point-transfer succeeded
     */
    public function transferBirthdayPoints($customer, $rule)
    {
        $currencyId      = $rule->getPointsCurrencyId();
        $pointAmount     = $rule->getPointsAmount();
        $customerId      = $customer->getId();
        $transfercomment = $this->getTransferComment();
        $transferStatus  = $this->getInitialTransferStatus();
        $transfer        = $this->create($customerId, $pointAmount, $currencyId, $transfercomment, $transferStatus);

        return ($transfer != null);
    }

    /**
     * create a transfer for birthday points
     * returns null if error;
     *
     * @param type $customerId
     * @param type $pointAmount
     * @param type $currencyId
     * @param type $comment
     * @param type $transferStatus
     * @return TBT_Rewards_Model_Birthday_Transfer null if error
     */
    public function create($customerId, $pointAmount, $currencyId, $comment, $transferStatus)
    {
        $pointAmount = (int) $pointAmount;
        if ($pointAmount <= 0) {
            return null;
        }

        $transfer = Mage::getModel('rewards/birthday_transfer');

        $transfer->setId(null)
                ->setCreationTs(now())
                ->setLastUpdateTs(now())
                ->setCustomerId($customerId)
                ->setQuantity($pointAmount)
                ->setCurrencyId($currencyId)
                ->setComments($comment)
                ->setReasonId(TBT_Rewards_Model_Birthday_Reason::REASON_TYPE_ID);

        if (false == $transfer->setStatus(null, $transfer->getInitialTransferStatus())) {
            return null;
        }

        $transfer->save();

        return $transfer;
    }

    /**
     *
     * @return TBT_Rewards_Helper_Birthday
     */
    protected function _getHelper()
    {
        return Mage::helper('rewards/birthday');
    }

}
