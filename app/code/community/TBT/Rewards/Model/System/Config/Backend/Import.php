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
 *
 * @category   TBT
 * @package    TBT_Rewards
 * @author     Sweet Tooth Inc. <support@sweettoothrewards.com>
 */
class TBT_Rewards_Model_System_Config_Backend_Import extends Mage_Core_Model_Config_Data
{

    /**
     * Starts import process
     *
     * @return self
     */
    protected function _afterSave()
    {
        $fields = $_FILES["groups"]["tmp_name"]["migration"]["fields"];
        if (isset($fields["import_campaign"])) {
            $tmp_fn = $fields["import_campaign"]["value"];
            if (!empty($tmp_fn)) {
                $this->_importCampaign($tmp_fn);
            }
        }

        if (isset($fields["importpoints"])) {
            $tmp_fn = $fields["importpoints"]["value"];
            if (!empty($tmp_fn)) {
                $this->importPoints($tmp_fn);
            }
        }

        if (isset($fields["import_settings"])) {
            $tmp_fn = $fields["import_settings"]["value"];
            if (!empty($tmp_fn)) {
                $this->_importSettings($tmp_fn);
            }
        }

        return parent::_afterSave();
    }

    /**
     * This handles importing a Sweet Tooth campaign into Sweet Tooth:
     *     catalog earning / spending rules
     *     cart earning / spending rules
     *     customer behavior earning rules including milestone rules
     *
     * @param  string $tmp_fn    Temporary file url.
     * @return self
     */
    protected function _importCampaign($tmp_fn)
    {
        try {
            $backup = Mage::getSingleton('rewards/migration_export')->getSerializedCampaignExport();

            Mage::getSingleton('rewards/migration_delete')->deleteOnlyRules();
            Mage::getSingleton('rewards/migration_import')->importFromFile($tmp_fn);

            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('rewards')->__('All Sweet Tooth rules were imported successfully.')
            );

            if (Mage::getSingleton('rewards/migration_import')->getIsCatalogRuleImported()) {
                Mage::app()->saveCache(1, 'catalog_rules_dirty');
                Mage::getSingleton('adminhtml/session')->addNotice(
                    Mage::helper('rewards')->__('You must still APPLY Sweet Tooth catalog earning/spending rules before they will take effect!')
                );
            }
        } catch (Exception $e) {
            Mage::getSingleton('rewards/migration_logger')->log($e);
            if (isset($backup)) {
                Mage::getSingleton('rewards/migration_import')->importFromSerializedData($backup);
            }
            throw $e;
        }

        return $this;
    }

    /**
     * This handles importing Sweet Tooth settings (configs) from file.
     *
     * @param  string $tmp_fn   Temporary file url.
     * @return self
     */
    protected function _importSettings($tmp_fn)
    {
        try {
            $helper = Mage::helper('rewards/migration');
            $params = $this->_getScopeParams();

            $backup = Mage::getSingleton('rewards/migration_export')
                ->setScopeParams($params)
                ->getSerializedConfigExport();

            Mage::getSingleton('rewards/migration_delete')
                ->setScopeParams($params)
                ->deleteAllRewardsConfig();

            Mage::getSingleton('rewards/migration_import')
                ->setScopeParams($params)
                ->importFromFile($tmp_fn);

            $helper->createAdminNotification('imported', $params);
        } catch (Exception $e) {
            Mage::getSingleton('rewards/migration_logger')->log($e);
            if (isset($backup)) {
                Mage::getSingleton('rewards/migration_import')->importFromSerializedData($backup);
            }
            throw $e;
        }

        return $this;
    }

    /**
     * Import Sweet Tooth customer points.
     *
     * @param  string $tmp_fn   Temporary file url.
     * @return self
     */
    protected function importPoints($tmp_fn)
    {
        try {
            Mage::getSingleton('rewards/migration_import')->importPoints($tmp_fn);
            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('rewards')->__('Points were imported successfully.')
            );
        } catch (Exception $e) {
            $messages = explode("\n", $e->getMessage());
            foreach ($messages as $message) {
                if (!empty ($message)) {
                    Mage::getSingleton('adminhtml/session')->addNotice(Mage::helper('rewards')->__($message));
                }
            }
            Mage::logException($e);
        }

        return $this;
    }

    /**
     * Retrieve current admin configuration scope details.
     *
     * @return array
     */
    protected function _getScopeParams()
    {
        $scopeParams = array(
            'website' => $this->getWebsiteCode(),
            'store'   => $this->getStoreCode(),
            'scope'   => $this->getScope()
        );

        return $scopeParams;
    }

    ////////////////
    // DEPRECATED //
    ////////////////

    /**
     * @deprecated
     * @see _importSettings()
     */
    protected function importConfig($tmp_fn)
    {
        return $this->_importSettings();
    }

    protected function importCampaign($tmp_fn)
    {
        return $this->_importCampaign($tmp_fn);
    }

}
