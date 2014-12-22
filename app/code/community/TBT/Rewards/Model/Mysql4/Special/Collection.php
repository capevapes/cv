<?php

class TBT_Rewards_Model_Mysql4_Special_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{

    public function _construct()
    {
        $this->_init('rewards/special');
    }

    /**
     * Joins the collection with the 'rewards_milestone_rule' table if Milestone module is enabled.
     * @return $this
     */
    public function addMilestoneRules()
    {
        if ($this->getFlag('milestone_rules') || !Mage::helper('rewards')->getIsMilestoneEnabled()) {
            return $this;
        }

        $this->getSelect()
            ->joinLeft(
                array('milestone_rule' => $this->getTable('tbtmilestone/rule')),
                "main_table.rewards_special_id = milestone_rule.rewards_special_id",
                array()
            )->columns(array(
                'milestone_rule.condition_type',
                'milestone_rule.condition_details_json',
                'milestone_rule.action_type',
                'milestone_rule.action_details_json'
            )
        );

        $this->setFlag('milestone_rules', true);

        return $this;
    }
}
