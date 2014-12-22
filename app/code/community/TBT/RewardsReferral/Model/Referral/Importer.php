<?php
/**
 * 
 * An implementation of TBT_Rewards_Model_Importer to import referrals from a CSV file through the cron
 *
 */
class TBT_RewardsReferral_Model_Referral_Importer extends TBT_Rewards_Model_Importer 
{	
	protected $_importerType = "rewardsref/referral_importer";	
	protected $_emailSubject = "Your referrals list has finished importing";
	protected $_csvHeaders = array();
	protected $_isDryRun = false;
	
	/* Store indices of titles on first line of csv file */
	protected $_indexOfReferrerEmail = -1;
	protected $_indexOfReferralEmail = -1;
	protected $_indexOfWebsiteId = -1;
		
	/**
	 * @see TBT_Rewards_Model_Importer::getAvailableOptions()
	 * @return array
	 */
	public function getAvailableOptions()
	{
		return array('validate_referral_emails', 'override', 'allow_non_existing_referrals');
	}
	
	/**
	 * Makes sure import file exists and CSV headers are appropriate
	 * @throw Exception if anything goes wrong
	 * @return TBT_RewardsReferral_Model_Referral_Importer
	 */
	public function validateFile()
	{
		$this->_isDryRun = true;
		$this->_doImport();
		return $this;
	}
	
	/**
	 * @param string $filename
	 * @throws Exception if there's a problem with the file
	 * @param boolean $isDryRun, true if we're only checking CSV format, false if we're actually importing data
	 * @param boolean $override, true if we should delete any pre-existing referrers a referral may have, before we link with a new referrer, false otherwise
	 * @param boolean $allowNonExistingReferrals, true if we should accept non-existing accounts as a referrer.
	 * @return TBT_RewardsReferral_Model_Import_Referral
	 */
	protected function _doImport() 
	{
		/* Extract options and paramaters from the importer base class */
		$filename = $this->getFile();
		$options = $this->getOptions();
		$isDryRun = $this->_isDryRun;
		$validateReferralEmails = isset($options['validate_referral_emails']) ? $options['validate_referral_emails'] : true;
		$override = isset($options['override']) ? $options['override'] : true;
		$allowNonExistingReferrals = isset($options['allow_non_existing_referrals']) ? $options['allow_non_existing_referrals'] : true;
				
		/* Throw an exception if we've already ran an import on this object instance */
		if ($this->_isImportFinished) throw new Exception("An import has already been executed in this object instance.");
		
		/* Local Variables */		
		$lineNumber = 0;
	
		/* Open file handle and read csv file line by line separating comma delaminated values */
		if (!file_exists($filename)) {
			throw new Exception("File doesn't exist: \"{$filename}\"");
		}
				
		$handle = fopen ( $filename, "r" );	
		while ( ($line = fgetcsv ( $handle, 1000, "," )) !== FALSE ) {
			if ($lineNumber === 0) {
				// Save the headers
				$this->_csvHeaders = $line;
				
				// This is the first line of the csv file. It usually contains titles of columns
				// Next iteration will propagate to "else" statement and increment to line 2 immediately
				$lineNumber = 1;
	
				/* Read in column headers and save indices if they appear */
				$num = count ( $line );
				for ($index = 0; $index < $num; $index ++) {
					$columnTitle = trim ( strtolower ( $line [$index] ) );
					if ($columnTitle === "referrer_email" || $columnTitle === "affiliate_email") {
						$this->_indexOfReferrerEmail = $index;
					}
					if ($columnTitle === "referral_email") {
						$this->_indexOfReferralEmail = $index;
					}
					if ($columnTitle === "website_id") {
						$this->_indexOfWebsiteId = $index;
					}
				}
				
				if ($this->_indexOfReferrerEmail === -1 || $this->_indexOfReferralEmail === -1) {
					throw new Exception("Invalid Column Headers. Expecting \"referrer_email, referral_email\" on first line of CSV");
				}
				
			} else {
				// Stop here if it's a dry-run, read the rest of the file otherwise
				if ($isDryRun) break;
				
				if ($lineNumber === 1) {
					$this->markStartOfImport();
				}
				
				try {					
					$lineNumber ++;
	
					/* Prepare line data based on values provided */
					$referrerEmail = trim($line[$this->_indexOfReferrerEmail]);
					$referralEmail = trim($line[$this->_indexOfReferralEmail]);
					
					if (empty($referrerEmail)) throw new Exception("No referrer email specified");
					if (empty($referralEmail)) throw new Exception("No referral email specified");
					
					if ($this->_indexOfWebsiteId != - 1) {
						$websiteId = array_key_exists ($this->_indexOfWebsiteId, $line ) ? $line [$this->_indexOfWebsiteId] : null;
					}

					// Customer email is website dependent. Either load deafult website or look at website ID provided in file
					if (empty($websiteId)) {
						$websiteId = Mage::app ()->getDefaultStoreView ()->getWebsiteId ();
					} else {
						$websiteId = Mage::app ()->getWebsite ( $websiteId )->getId ();
					}
					
					$referrerCustomer = Mage::getModel ( 'customer/customer' )->setWebsiteId ( $websiteId )->loadByEmail ( $referrerEmail );					
					if (!$referrerCustomer->getId ()) {
						throw new Exception("Cannot find referrer \"{$referrerEmail}\"");
					}
					
					$referralCustomer = Mage::getModel ( 'customer/customer' )->setWebsiteId ( $websiteId )->loadByEmail ( $referralEmail );
					if (!$referralCustomer->getId ()) {
						$warningMessage = "Referral account doesn't exist \"{$referralEmail}\"";
						if (!$allowNonExistingReferrals) {
							throw new Exception($warningMessage);
						}
						$this->_reportWarning($warningMessage, $line, $lineNumber);						
						if (!filter_var($referralEmail, FILTER_VALIDATE_EMAIL)) {
							$warningMessage = "Referral is not a customer and email is invalid: \"{$referralEmail}\"";
							if ($validateReferralEmails) {
								throw new Exception($warningMessage);
							}
							$this->_reportWarning($warningMessage, $line, $lineNumber);
						}
						$successMessage = $this->_linkAccounts($referrerCustomer, $referralEmail, $override);
					} else {
						$successMessage = $this->_linkAccounts($referrerCustomer, $referralCustomer, $override);
					}
					
					$this->_reportSuccess($successMessage, $line, $lineNumber);
					
				} catch ( Exception $e ) {
					// Any other errors which happen on each line should be saved and reported at the very end
					$this->_reportError($e->getMessage(), $line, $lineNumber);
				}

				// Don't count the first line (titles)
				$this->_countItemsProcessed = $lineNumber - 1;					
				$this->setCountProcessed($this->_countItemsProcessed);
				$this->save();
			}
		}
		
		fclose ( $handle );
		
		if ($lineNumber === 0) {
			throw new Exception("Empty file: \"{$filename}\"");	
		}
	
		if ($this->_printToStdOut) {
			echo "\n\nImport complete!\n";
		}
		
		$this->markEndOfImport();
				
		return $this;
	}
	
	/**
	 * Will generate a 1 line summary of the report.
	 * @return string
	 */
	public function getReportSummary()
	{
		if (!$this->_isImportFinished) {
			throw new Exception("Please run the importer first.");
		}
		
		return "<b>" . count($this->_successes) . " out of " . $this->_countItemsProcessed . "</b> referral links have been successfully imported. ";
	}
	
	/**
	 * Will generate a report of the import which was recently completed by this instance.
	 * 
	 * @throws Exception if the import has not been run yet.
	 * @return string a report containing all the errors, warnings and successes in the import
	 */
	public function getReport()
	{
		if (!$this->_isImportFinished) {
			throw new Exception("Please run the importer first.");
		}
		
		if (!empty($this->_report)) return $this->_report;
		
		$report = $this->getReportSummary();
		$report .= "Details are below:\n\n\n\n";
		
		if (count($this->_errors) > 0) {
			$report .= "<b><i>" . count($this->_errors) . " enteries</i></b> <b style=\"color: red;\">failed</b> because of errors:\n\n";
			$report .= "<table  border=\"1\" cellpadding=\"5\" cellspacing=\"0\" width=\"80%\" style=\"border: 1px solid black; border-collapse: collapse;\">";
			$report .= "	<tr>";
			$report .= "		<th style=\"text-align:left\">line_number</th>";
			foreach ($this->_csvHeaders as $header){
				$report .= "	<th style=\"text-align:left\">{$header}</th>";
			}
			$report .= "		<th style=\"text-align:left\">error_message</th>";
			$report .= "	</tr>";
			foreach ($this->_errors as $item) {
				$report .= "	<tr>";
				$report .= "		<td>{$item["lineNumber"]}</td>";
				foreach ($item["line"] as $param) {
					$report .= "	<td>{$param}</td>";
				}
				$report .= "		<td>" . str_replace(",", ";", $item["message"]) . "</td>";
				$report .= "	</tr>";
			}
			$report .= "</table>";
			$report .= "\n\n\n";
		}


		if (count($this->_warnings) > 0) {
			$report .= "<b><i>" . count($this->_warnings) . " enteries</i></b> produced <b style=\"color: orange;\">warnings</b>:\n\n";
			$report .= "<table  border=\"1\" cellpadding=\"5\" cellspacing=\"0\" width=\"80%\" style=\"border: 1px solid black; border-collapse: collapse;\">";
			$report .= "	<tr>";
			$report .= "		<th style=\"text-align:left\">line_number</th>";
			foreach ($this->_csvHeaders as $header){
				$report .= "	<th style=\"text-align:left\">{$header}</th>";
			}
			$report .= "		<th style=\"text-align:left\">warning_message</th>";
			$report .= "	</tr>";		
			foreach ($this->_warnings as $item) {
				$report .= "	<tr>";
				$report .= "		<td>{$item["lineNumber"]}</td>";
				foreach ($item["line"] as $param) {
					$report .= "	<td>{$param}</td>";
				}
				$report .= "		<td>" . str_replace(",", ";", $item["message"]) . "</td>";
				$report .= "	</tr>";
			}
			$report .= "</table>";
			$report .= "\n\n\n";
		}
		

		$report .= "<b><i>" . count($this->_successes) . " enteries</i></b> were imported <b style=\"color: green;\">successfully</b>:\n\n";
		if (count($this->_successes) > 0){
			$report .= "<table  border=\"1\" cellpadding=\"5\" cellspacing=\"0\" width=\"80%\" style=\"border: 1px solid black; border-collapse: collapse;\">";
			$report .= "	<tr>";
			foreach ($this->_csvHeaders as $header){
				$report .= "	<th style=\"text-align:left\">{$header}</th>";
			}
			$report .= "	</tr>";
			foreach ($this->_successes as $item) {
				$report .= "	<tr>";
				foreach ($item["line"] as $param) {
					$report .= "	<td>{$param}</td>";
				}
				$report .= "	</tr>";
			}
			$report .= "</table>";
		}
				
		$this->_report = nl2br($report);
		return $this->_report;
	}
	
	/**
	 * Will link the two referral and referrer customers supplied
	 * 
	 * @throws Exception if something goes wrong
	 * @param Mage_Customer_Model_Customer $referrerCustomer
	 * @param Mage_Customer_Model_Customer|string $referralCustomer, either full object or an email address (weak link)
	 * @param boolean $override, whether to replace existing referral connecitons if they exist.
	 * @return sting success message
	 */
	protected function _linkAccounts($referrerCustomer, $referralCustomer, $override = true) 
	{
		if ($override) {
			$overrideMessage = $this->_deleteExistingReferralLinks($referralCustomer);
		}
		
		$referrerEmail = $referrerCustomer->getEmail();
		$newLink = Mage::getModel('rewardsref/referral');
				
		if (is_string($referralCustomer)) {
			// It's probably just an email address :(
			$newLink->setReferralEmail($referralCustomer);
			$message = "\"{$referralCustomer}\" will become a refferral of \"{$referrerEmail}\" when they signup";
			
		} else {
			$newLink->setReferralChildId($referralCustomer->getId())
					->setReferralEmail($referralCustomer->getEmail())
					->setReferralName($referralCustomer->getName());
			$message = "\"{$referralCustomer->getEmail()}\" is now a refferral of \"{$referrerEmail}\"";
		}
		
		$newLink->setReferralParentId($referrerCustomer->getId())
				->setOverrideSignupRestriction(true)
				->save();
		
		return $message . (!empty($overrideMessage) ? "\n{$overrideMessage}" : "");
	}
	
	/**
	 * Will accept a customer and delete any links it has with a referrer, effectively making it no-one's referral.
	 * @throws Exception if $referralCustomer has no Email and no ID
	 * @param Mage_Customer_Model_Customer|string $referralCustomer, either the customer object if one exists, or their email address if it doesn't.
	 * @return string success message
	 */
	protected function _deleteExistingReferralLinks($referralCustomer) 
	{
		$message = "";
		if (is_string($referralCustomer)) {
			$customerEmail = $referralCustomer;
			
		} else {
			$customerId = $referralCustomer->getId();
			$customerEmail = $referralCustomer->getEmail();				
		}
		
		if (empty($customerId) && empty($customerEmail)) {
			throw new Exception('Referral customer has no Email nor an ID.');
		}

		$existingLinks = Mage::getModel('rewardsref/referral')->getCollection();
		$existingLinks->getSelect()
							->where("referral_child_id = ?", $customerId)
							->orWhere("referral_email = ?", $customerEmail);

		foreach ($existingLinks as $link) {
			// Load original referrer
			$existingReferrer = Mage::getModel('customer/customer')->load($link->getReferralParentId());
			$link->delete();
			$link->save();
			if ($existingReferrer->getId()){
				$email = $existingReferrer->getEmail();
				$message .= "\t * removed pre-existing referral link with \"{$email}\"\n";
			}
		}
		
		return $message;
	}
}