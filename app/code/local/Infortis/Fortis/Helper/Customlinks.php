<?php

class Infortis_Fortis_Helper_Customlinks extends Mage_Core_Helper_Abstract
{
	/**
	 * Get sign up label.
	 * If top links with icons enabled, return empty label for the standard sign up link so that the link can be omitted (to avoid duplicate).
	 *
	 * @return string
	 */
	public function getSignupLabel()
	{
		if (Mage::getStoreConfig('fortis/header/top_links_icons'))
		{
			return '';
		}
		return 'Sign Up';
	}
}
