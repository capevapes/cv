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
 * Cart Display Helper
 *
 * @category   TBT
 * @package    TBT_Rewards
 * * @author     Sweet Tooth Inc. <support@sweettoothrewards.com>
 */
class TBT_Rewards_Helper_Cart extends Mage_Core_Helper_Abstract {
	
	// any type of redemptions, cart and catalog
	public function cartHasRedemptions() {
		return $this->_getRewardsSess ()->hasRedemptions ();
	}
	
	// any type of redemptions, cart and catalog
	public function cartHasDistributions() {
		return $this->_getRewardsSess ()->hasDistributions ();
	}
	
	public function showPointsColumn() {
		// $has_any_rules_at_all = ($this->cartHasDistributions() || $this->cartHasRedemptions());
		$store_has_any_catalog_rules = Mage::helper ( 'rewards/rule' )->storeHasAnyPointsCatalogRules ();
		$store_has_any_catalog_distri_rules = Mage::helper ( 'rewards/rule' )->storeHasAnyCatalogDistriRules ();
		$cart_has_catalog_redem = $this->_getRewardsSess ()->getQuote ()->hasAnyAppliedCatalogRedemptions ();
		$show_points_colmn = $store_has_any_catalog_distri_rules || ($store_has_any_catalog_rules && $cart_has_catalog_redem);
		return $show_points_colmn;
	}
	
	public function showPointsAdditionalSubsection() {
		$store_has_any_catalog_distri_rules = Mage::helper ( 'rewards/rule' )->storeHasAnyCatalogDistriRules ();
		return $store_has_any_catalog_distri_rules;
	}
	
	public function showBeforePointsColumn() {
		return $this->cartHasAnyCatalogRedemptions ();
	}
	
	public function cartHasAnyCatalogRedemptions() {
		return $this->_getRewardsSess ()->getQuote ()->hasAnyAppliedCatalogRedemptions ();
	}
	
	public function showCartRedeemBox($storeId = null) {
		// $has_any_rules_at_all = ($this->cartHasDistributions() || $this->cartHasRedemptions());
		$store_has_any_cart_rules = Mage::helper ( 'rewards/rule' )->storeHasAnyPointsShoppingCartRules ($storeId);
		return $store_has_any_cart_rules;
	}
	
	/**
	 * Fetchtes the rewards cofnig helper
	 *
	 * @return TBT_Rewards_Helper_Config
	 */
	public function getCfgHelper() {
		return Mage::helper ( 'rewards/config' );
	}
	
	/**
	 * Fetches the rewards session singleton
	 *
	 * @return TBT_Rewards_Model_Session
	 */
	protected function _getRewardsSess() {
		return Mage::getSingleton ( 'rewards/session' );
	}

}