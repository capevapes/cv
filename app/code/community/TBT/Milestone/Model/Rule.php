<?php

/**
 * @method self setConditionDetails() setConditionDetails(array $details)
 * @method array getConditionDetails()
 * @method self setActionDetails() setActionDetails(array $details)
 * @method array getActionDetails()
 */
class TBT_Milestone_Model_Rule extends Mage_Core_Model_Abstract
{
    protected $_condition = null;
    protected $_action = null;


    protected function _construct()
    {
        parent::_construct();

        $this->_init('tbtmilestone/rule');

        return $this;
    }

    public function trigger($customerId)
    {
        if (!$this->getIsEnabled()) {
            // TODO: possibly throw, since we shouldn't be reaching this anyway?
            return $this;
        }

        $condition = $this->getCondition();
        if (!$condition->isSatisfied($customerId)) {
            return $this;
        }

        $action = $this->getAction();

        $action->setCustomerId($customerId);
        $action->execute($customerId);

        $currentTimestamp = Mage::helper('tbtmilestone')->getUtcTimestamp();
        Mage::getModel('tbtmilestone/rule_log')->setRuleId($this->getId())
            ->setRuleName($this->getName())
            ->setConditionType($this->getConditionType())
            ->setActionType($this->getActionType())
            ->setMilestoneDetails($this->getMilestoneDetails())
            ->setCustomerId($customerId)
            ->setExecutedDate($currentTimestamp)
            ->save();

        return $this;
    }

    public function getMatchingRules($conditionTypes)
    {
        if (!is_array($conditionTypes)) {
            $conditionTypes = array($conditionTypes);
        }

        $rules = $this->getCollection()
                      ->addFieldToFilter('is_enabled', 1)
                      ->addFieldToFilter('condition_type', array('in' => $conditionTypes));

        return $rules;
    }

    protected function _afterLoad()
    {
        parent::_afterLoad();

        // Explode the CSV strings into arrays so they're easier to manage in code.
        if ($this->hasWebsiteIds()) {
            $websiteIds = $this->getWebsiteIds();
            if (is_string($websiteIds) && !empty($websiteIds)) {
                $this->setWebsiteIds(explode(',', $websiteIds));
            }
        }

        if ($this->hasCustomerGroupIds()) {
            $customerGroupIds = $this->getCustomerGroupIds();
            if (is_string($customerGroupIds) && !empty($customerGroupIds)) {
                $this->setCustomerGroupIds(explode(',', $customerGroupIds));
            }
        }

        $this->unserializeConditions();
        $this->unserializeActions();

        return $this;
    }

    protected function _beforeSave()
    {
        parent::_beforeSave();

        // Ask the child condition and action objects if their requirements have been met before saving.
        $this->getCondition()->validateSave();
        $this->getAction()->validateSave();

        // TODO: This is UTC, but perhaps it should be set with local.  Or created_at should be set in code.
        $this->setUpdatedAt(now());

        // Implode the arrays into CSV strings so they can be saved.
        if ($this->hasWebsiteIds()) {
            $websiteIds = $this->getWebsiteIds();
            if (is_array($websiteIds) && !empty($websiteIds)) {
                $this->setWebsiteIds(implode(',', $websiteIds));
            }
        }

        if ($this->hasCustomerGroupIds()) {
            $customerGroupIds = $this->getCustomerGroupIds();
            if (is_array($customerGroupIds) && !empty($customerGroupIds)) {
                $this->setCustomerGroupIds(implode(',', $customerGroupIds));
            }
        }

        $this->serializeConditions();
        $this->serializeActions();

        return $this;
    }

    /**
     * Processes this rule and unserializes the conditions json, which is how conditions are saved in the DB, into an
     * associative array.
     *
     * @return self
     */
    public function unserializeConditions()
    {
        $conditionDetailsJson = $this->getConditionDetailsJson();
        if (!$conditionDetailsJson) {
            return $this;
        }

        $conditionDetails = json_decode($conditionDetailsJson, true);
        if ($conditionDetails && is_array($conditionDetails)) {
            $this->setConditionDetails($conditionDetails);
        }

        return $this;
    }

    /**
     * Processes this rule and unserializes the actions json, which is how actions are saved in the DB, into an
     * associative array.
     *
     * @return self
     */
    public function unserializeActions()
    {
        $actionDetailsJson = $this->getActionDetailsJson();
        if (!$actionDetailsJson) {
            return $this;
        }

        $actionDetails = json_decode($actionDetailsJson, true);
        if ($actionDetails && is_array($actionDetails)) {
            $this->setActionDetails($actionDetails);
        }

        return $this;
    }

    /**
     * Serializes the conditions array into JSON, as it's stored in the DB.
     *
     * @return self
     */
    public function serializeConditions()
    {
        $conditionDetails = $this->getConditionDetails();
        if (!$conditionDetails) {
            return $this;
        }

        $conditionDetailsJson = json_encode($conditionDetails);
        if (json_last_error() == JSON_ERROR_NONE) {
            // TODO: log error if one occurred
            $this->setConditionDetailsJson($conditionDetailsJson);
        }

        return $this;
    }

    /**
     * Serializes the actions array into JSON, as it's stored in the DB.
     *
     * @return self
     */
    public function serializeActions()
    {
        $actionDetails = $this->getActionDetails();
        if (!$actionDetails) {
            return $this;
        }

        $actionDetailsJson = json_encode($actionDetails);
        if (json_last_error() == JSON_ERROR_NONE) {
            // TODO: log error if one occurred
            $this->setActionDetailsJson($actionDetailsJson);
        }

        return $this;
    }


    /**
     * Based on the Website Ids supported by this rule, generates and returns a list of Store Ids which this rule also
     * applies to.
     * @see TBT_Milestone_Helper_Data::getStoreIdsFromWebsites()
     * @return array
     */
    public function getStoreIds()
    {
        if (!isset($this->_storeIds)) {
            $this->_storeIds = Mage::helper('tbtmilestone')->getStoreIdsFromWebsites($this->getWebsiteIds());
        }

        return $this->_storeIds;
    }


    /**
     * @return TBT_Milestone_Model_Rule_Condition_Revenue
     */
    public function getCondition()
    {
        if (!$this->hasLoadedCondition()) {
            $this->_condition = $this->_getConditionFactory()->create($this->getConditionType())
                                     ->setData($this->getConditionDetails())
                                     ->setRule($this);
        }

        return $this->_condition;
    }

    /**
     * @return TBT_Milestone_Model_Rule_Action_Customergroup
     */
    public function getAction()
    {
        if (!$this->hasLoadedAction()) {
            $this->_action = $this->_getActionFactory()->create($this->getActionType())
                                  ->setData($this->getActionDetails())
                                  ->setRule($this);
        }

        return $this->_action;
    }

    public function addConditionDetail($attribute, $value)
    {
        $details = $this->getConditionDetails();
        if (!is_array($details)) {
            $details = array();
        }

        $details[$attribute] = $value;
        $this->setConditionDetails($details);

        return $this;
    }

    public function addActionDetail($attribute, $value)
    {
        $details = $this->getActionDetails();
        if (!is_array($details)) {
            $details = array();
        }

        $details[$attribute] = $value;
        $this->setActionDetails($details);

        return $this;
    }

    public function hasLoadedCondition()
    {
        return isset($this->_condition);
    }

    public function hasLoadedAction()
    {
        return isset($this->_action);
    }

    public function getDataForForm($data = null)
    {
        return $this->_getDataForForm($this->getData());
    }

    /**
     * Whether or not this rule was ever executed for the specified customer
     *
     * @param int $customerId
     *
     * @return boolean
     */
    public function wasEverExecuted($customerId)
    {
        return Mage::getModel('tbtmilestone/rule_log')->wasRuleEverExecuted($this->getId(), $customerId);
    }

    protected function _getDataForForm($data, $parentKey = null)
    {
        $dataForForm = array();
        foreach ($data as $key => $value) {
            $realKey = ($parentKey ? $parentKey . '_' : '') . $key;
            if (is_array($value) && Mage::helper('tbtcommon/array')->isAssoc($value)) {
                $dataForForm += $this->_getDataForForm($value, $realKey);
            } else {
                $dataForForm[$realKey] = $value;
            }
        }

        return $dataForForm;
    }

    /**
     * @return TBT_Milestone_Model_Rule_Condition_Factory
     */
    protected function _getConditionFactory()
    {
        return Mage::getSingleton('tbtmilestone/rule_condition_factory');
    }

    /**
     * @return TBT_Milestone_Model_Rule_Action_Factory
     */
    protected function _getActionFactory()
    {
        return Mage::getSingleton('tbtmilestone/rule_action_factory');
    }
}
