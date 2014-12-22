<?php

/**
 * Model to handle cron tasks logic
 *
 * @author Ebizmarts Team <info@ebizmarts.com>
 */
class Ebizmarts_SweetMonkey_Model_Cron {

    /**
     * Push customers vars to MailChimp
     *
     * @return void
     */
    public function pushMergeVarsForCustomers() {

        $customers = Mage::getModel('rewards/customer')->getCollection();

        foreach ($customers as $c) {
            if (!Mage::helper('rewards/expiry')->isEnabled($c->getStoreId())) {
                continue;
            }

            $customer = Mage::getModel('rewards/customer')->load($c->getId());
            Mage::helper('sweetmonkey')->pushVars($customer);
        }
    }

}