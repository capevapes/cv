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
 * Observer Sales Convert Quote To Order
 *
 * @category   TBT
 * @package    TBT_Rewards
 * * @author     Sweet Tooth Inc. <support@sweettoothrewards.com>
 */
class TBT_Rewards_Model_Observer_Sales_Convert_Quotetoorder implements TBT_Rewards_Model_Customer_Listener
{
    /**
     * Quotation model by reference for quoteToOrder conversions event
     *
     * @var TBT_Rewards_Model_Sales_Quote
     */
    protected $quote = null;
    /**
     * Order model by reference for quoteToOrder conversions event
     *
     * @var TBT_Rewards_Model_Sales_Order
     */
    protected $order = null;

    protected $_reservedIncrementId = null;

    public function __construct()
    {
    }

    /**
     * Applies the special price percentage discount
     *
     * @param   Varien_Event_Observer $observer
     * Mage::dispatchEvent('sales_convert_quote_to_order', array('order'=>$order, 'quote'=>$quote));
     *
     * @return  Xyz_Catalog_Model_Price_Observer
     */
    public function prepareCatalogPointsTransfers($observer)
    {
        $event = $observer->getEvent();

        //@nelkaake -a 17/02/11: Save quote info in order
        $this->copyQuoteRewardsDataToOrder($event->getQuote(), $event->getOrder());

        $this->quote = $event->getQuote();
        $this->order = $event->getOrder();

        if (Mage::helper('rewards')->isMultishipMode($this->quote)) {
            return $this;
        }
        if (!$this->quote || !$this->order) {
            return $this;
        }

        //@nelkaake Added on Thursday May 27, 2010: If mage 1.4 then add "true" tothe checkout method get function
        if (Mage::helper('rewards')->isBaseMageVersionAtLeast('1.4.0.0')) {
            if ($this->quote->getCheckoutMethod(true) == Mage_Sales_Model_Quote::CHECKOUT_METHOD_REGISTER) {
                $this->quote->validateQuoteToOrderTransfers();
                //              Mage::helper('rewards')->notice("Checkout method is REGISTER so added customer listener in TBT_Rewards_Model_Observer_Sales_Convert_Quotetoorder...");
                $this->quote->reserveOrderId();
                $this->_reservedIncrementId = $this->quote->getReservedOrderId();
                $this->_getRewardsSession()->addCustomerListener($this);
            } else {
                $this->quote->collectQuoteToOrderTransfers();
            }
        } else {
            if ($this->quote->getCheckoutMethod() == Mage_Sales_Model_Quote::CHECKOUT_METHOD_REGISTER) {
                $this->quote->validateQuoteToOrderTransfers();
                //              Mage::helper('rewards')->notice("Checkout method is REGISTER so added customer listener in TBT_Rewards_Model_Observer_Sales_Convert_Quotetoorder...");
                $this->quote->reserveOrderId();
                $this->_reservedIncrementId = $this->quote->getReservedOrderId();
                $this->_getRewardsSession()->addCustomerListener($this);
            } else {
                $this->quote->collectQuoteToOrderTransfers();
            }
        }

        return $this;
    }

    /**
     *
     * Writes rewards data from the quote model into the order model
     *
     * @param TBT_Rewards_Model_Sales_Quote $quote
     * @param TBT_Rewards_Model_Sales_Order $order
     */
    public function copyQuoteRewardsDataToOrder($quote, $order)
    {

        $order->setRewardsDiscountAmount($quote->getRewardsDiscountAmount());
        $order->setRewardsDiscountTaxAmount($quote->getRewardsDiscountTaxAmount());
        $order->setRewardsBaseDiscountAmount($quote->getRewardsBaseDiscountAmount());
        $order->setRewardsBaseDiscountTaxAmount($quote->getRewardsBaseDiscountTaxAmount());

        return $this;
    }

    /**
     * Reads in all the rewards data from the quote to the order
     *
     *   Mage::dispatchEvent('sales_convert_quote_item_to_order_item',
     *       array('order_item'=>$orderItem, 'item'=>$item)
     *   );
     */
    public function savePointsInfoInOrder($observer)
    {

        $event = $observer->getEvent();
        if (!$event) {
            return $this;
        }

        $orderItem = $observer->getEvent()->getOrderItem();
        $quoteItem = $observer->getEvent()->getItem();
        if (!$quoteItem || !$orderItem) {
            return $this;
        }

        $redeemModel = Mage::getSingleton('rewards/redeem');
        $redeemModel->refactorRedemptions($quoteItem);

        if ($quoteItem->getRowTotalAfterRedemptions() == null || $quoteItem->getRowTotalAfterRedemptionsInclTax(
            ) == null
        ) {
            $quoteItem->setRowTotalAfterRedemptions($redeemModel->getRowTotalAfterRedemptions($quoteItem));
            $quoteItem->setRowTotalAfterRedemptionsInclTax(
                $redeemModel->getRowTotalAfterRedemptionsInclTax($quoteItem)
            );
        }

        $orderItem->setRowTotal($quoteItem->getRowTotal());
        $orderItem->setBaseRowTotal($quoteItem->getBaseRowTotal());
        $orderItem->setRowTotalInclTax($quoteItem->getRowTotalInclTax());
        $orderItem->setBaseRowTotalInclTax($quoteItem->getBaseRowTotalInclTax());
        $orderItem->setTaxAmount($quoteItem->getTaxAmount());
        $orderItem->setBaseTaxAmount($quoteItem->getBaseTaxAmount());
        $orderItem->setEarnedPointsHash($quoteItem->getEarnedPointsHash());
        $orderItem->setRedeemedPointsHash($quoteItem->getRedeemedPointsHash());
        $orderItem->setRowTotalBeforeRedemptions($quoteItem->getRowTotalBeforeRedemptions());
        $orderItem->setRowTotalBeforeRedemptionsInclTax($quoteItem->getRowTotalBeforeRedemptionsInclTax());
        $orderItem->setRowTotalAfterRedemptions($quoteItem->getRowTotalAfterRedemptions());
        $orderItem->setRowTotalAfterRedemptionsInclTax($quoteItem->getRowTotalAfterRedemptionsInclTax());
        $orderItem->setRowTotalWithDiscount($quoteItem->getRowTotalWithDiscount());

        return $this;
    }

    /**
     * Observes 'sales_convert_quote_address_to_order' and updates the order with any rewards catlog discount tax
     * amount.
     *
     * @param  Varien_Event_Observer $observer [description]
     *
     * @return self
     */
    public function saveRewardsDiscountTaxAmount($observer)
    {
        $event = $observer->getEvent();
        if (!$event) {
            return $this;
        }

        $address = $event->getAddress();
        $order   = $event->getOrder();
        if (!$address || !$order) {
            return $this;
        }

        $storeId       = $order->getStoreId();
        $pricesInclTax = Mage::getSingleton('tax/config')->priceIncludesTax($storeId);

        $subtotal            = $order->getSubtotal() - $order->getRewardsDiscountAmount();
        $subtotalInclTax     = $order->getSubtotalInclTax() - $order->getRewardsDiscountAmount();
        $baseSubtotal        = $order->getBaseSubtotal() - $order->getRewardsBaseDiscountAmount();
        $baseSubtotalInclTax = $order->getBaseSubtotalInclTax() - $order->getRewardsBaseDiscountAmount();

        // only if prices incl tax we need adust tax to take into account rewards catalog discount tax
        if ($pricesInclTax) {
            $taxAmount     = max($order->getTaxAmount() + $address->getRewardsTaxDiscount(), 0);
            $taxBaseAmount = max($order->getBaseTaxAmount() + $address->getRewardsBaseTaxDiscount(), 0);
            // if prices include tax we need to take into account STR catalog discount tax
            $subtotal     += $order->getRewardsDiscountTaxAmount();
            $baseSubtotal += $order->getRewardsBaseDiscountTaxAmount();

            $order->setTaxAmount($taxAmount);
            $order->setBaseTaxAmount($taxBaseAmount);
        } else {
            $subtotalInclTax     -= $order->getRewardsDiscountTaxAmount();
            $baseSubtotalInclTax -= $order->getRewardsBaseDiscountTaxAmount();
        }

        $subtotal            = max($subtotal, 0);
        $subtotalInclTax     = max($subtotalInclTax, 0);
        $baseSubtotal        = max($baseSubtotal, 0);
        $baseSubtotalInclTax = max($baseSubtotalInclTax, 0);

        $order->setSubtotal($subtotal);
        $order->setSubtotalInclTax($subtotalInclTax);
        $order->setBaseSubtotal($baseSubtotal);
        $order->setBaseSubtotalInclTax($baseSubtotalInclTax);

        return $this;
    }

    /**
     * Fetches the rewards session
     *
     * @return TBT_Rewards_Model_Session
     */
    protected function _getRewardsSession()
    {
        return Mage::getSingleton('rewards/session');

    }

    /**
     * Triggered when customer model is created
     *
     * @param TBT_Rewards_Model_Customer $customer
     *
     * @return TBT_Rewards_Model_Customer_Listener
     */
    public function onNewCustomerCreate(&$customer)
    {
        //Mage::helper('rewards')->notice("Triggered customer registration listener to generate order catalog points in TBT_Rewards_Model_Observer_Sales_Convert_Quotetoorder.");
        $this->quote->collectQuoteToOrderTransfers(false);
        Mage::getSingleton('rewards/observer_sales_catalogtransfers')->setIncrementId($this->_reservedIncrementId);

        return $this;
    }
}
