<?php

/**
 * This abstract class implements the driver for any class that wishes to process cron imports
 * @abstract
 */
abstract class TBT_Rewards_Model_Importer extends Mage_Core_Model_Abstract 
{
	const STATUS_ENQUEUED = 0;		// import hasn't started yet
	const STATUS_PROCESSING = 1;	// import is currently in process
	const STATUS_COMPLETE = 2;		// import has already completed
	const STATUS_ERROR = 3;			// import never completed due to an error

	protected $_importerType = null;	// subclasses must overwrite this
	protected $_emailSubject = "Import Complete";
	protected $_printToStdOut = false;
	protected $_isImportFinished = false;
	protected $_countItemsProcessed = 0;
	
	protected $_errors = array();
	protected $_successes = array();
	protected $_warnings = array();
	
	/**
	 * 
	 * @param int $status, the status number to filter the collection by
	 * @return TBT_Rewards_Model_Mysql4_Importer_Collection, collection of importers
	 */
	public static function getImportersCollection($status = null)
	{
		$colelctionModel = Mage::getResourceModel('rewards/importer_collection', Mage::getResourceSingleton('rewards/importer'));
		
		if (!is_null($status)) {
			$colelctionModel->addFieldToFilter('status', $status);
		}
		
		return $colelctionModel;
	}
	
	protected function _construct()
	{		
		$this->_init ( 'rewards/importer' );
		parent::_construct();
	}
	
	/**
	 * Will enqueue a file, by validating it, uploading it to the var/imports folder
	 * and saving the model
	 * 
	 * @param string $fileIndex, the index of an uploaded file in the $_FILES array
	 * @param string $email, the email address to enqueue the importer with and send reports to later
	 * @param array $options, array of options for the importer
	 * @return TBT_Rewards_Model_Importer
	 */
	public function enqueue($fileIndex, $email, $options = array())
	{
		if (empty($fileIndex) || empty($_FILES[$fileIndex])){
			throw new Exception('Unable to detect any uploaded files.');
		}
		
		$filename = $_FILES[$fileIndex]['tmp_name'];
		if (!file_exists($filename)) {
			throw new Exception('Unable to read uploaded file.');
		}
				
		$this->setFile($filename)
				->setOriginalFilename($_FILES[$fileIndex]['name'])
				->validateFile();
		
		/* Set the total count on the importer */
		$totalLines = count(file($filename));
		$linesToImport = max(0, $totalLines - 1);
		$this->setCountTotal($linesToImport);
		
		/* Upload the file into the var/imports folder */
		$uploader = new Varien_File_Uploader($fileIndex);
		$uploader->setAllowedExtensions(array('csv'));
		$uploader->setAllowRenameFiles(false);
		$uploader->setAllowCreateFolders(true);
		
		$temporaryName = basename($filename);
		$path = Mage::getBaseDir('var') . DS . "imports";
		$savedFile = $uploader->save($path, $temporaryName);
		if (!$savedFile) {			
			throw new Exception("Unable to save file for processing");
		}
		
		$newFilename = $savedFile['path'] . DS . $savedFile['file'];
		if (!file_exists($newFilename)){
			throw new Exception("Unable to locate uploaded file");
		}
		
		$this->setFile($newFilename)
				->setEmail($email)
				->setOptions($options)
				->save();
		
		return $this;
	}
	
	/**
	 * Read and validate the input file before enqueuing
	 * Subclasses should implement this.
	 * 
	 * @throws Exception if something is invalid
	 * @param string $filename location of the file
	 * @return TBT_Rewards_Model_Importer true if file is valid
	 */
	public function validateFile() 
	{
		return $this;	
	}
	
	/**
	 * Sub-classes should implement this
	 * @return array, list of available options
	 */
	public function getAvailableOptions()
	{
		return array();
	}
	
	/**
	 * Will attempt an import (based on the implementing subclass).
	 * If any errors occur, the importer will be marked with an ERROR and saved
	 * and an email containing the error message will be sent out
	 * If import passes, a report will be emailed out.  
	 * @return TBT_Rewards_Model_Importer
	 */
	public function import() 
	{
		$email = $this->getEmail();
		try {
			$this->_doImport();
			
		} catch (Exception $e) {			
			$this->markImportError();
			if (!empty($email)) {
				$this->_sendEmail("There was an error starting your import", $email, $e->getMessage());
			}
			
			if ($this->_printToStdOut) {
			 echo "[EXCEPTION] " . $e->getMessage() . "\n";	
			}
			
			return $this;
		}
		
		if (!empty($email)) {
			$this->emailReport();
		}
		
		return $this;
	}
	
	/**
	 * Abstract method which subclasses should implement for the import functionality
	 * @throws Exception
	 * @abstract
	 */
	protected abstract function _doImport();
	
	/**
	 * Will generate a 1 line summary of the report.
	 * Subclasses should implement this.
	 * @return string
	 */
	public function getReportSummary()
	{
		return "";
	}
	
	/**
	 * Will generate a report of the import which was recently completed by this instance.
	 * Subclasses should implement this.
	 * @return string
	 */
	public function getReport()
	{
		return "";
	}
	
	/**
	 * Set a timestamp for when the import started and
	 * set the status of the importer to "PROCESSING"
	 * Also saves the model.
	 * @return TBT_Rewards_Model_Importer
	 */
	public function markStartOfImport() 
	{
		return $this->setStartedAt($this->_getUtcTimestamp())
					->setStatus(self::STATUS_PROCESSING)
					->save();
	}
	
	/**
	 * If the import is currently in "PROCESSING" status,
	 * set a timestamp for when the import ended and
	 * set the status of the importer to "COMPLETE"
	 * Also saves the model.
	 * 
	 * @return TBT_Rewards_Model_Importer
	 */
	public function markEndOfImport()
	{
		if ($this->getStatus() === self::STATUS_PROCESSING) {
			$this->_isImportFinished = true;
			$this->setEndedAt($this->_getUtcTimestamp())
				 ->setStatus(self::STATUS_COMPLETE)
				 ->save();
		}
		
		return $this;
	}
	
	/**
	 * Regardless of the current status, will set the import status to ERROR
	 * and will save the model.
	 * 
	 * @return TBT_Rewards_Model_Importer
	 */
	public function markImportError()
	{
		return $this->setStatus(self::STATUS_ERROR)
					->save();	
	}
	
	/**
	 * Will decode the importer options from a JSON format
	 * @return array importer options
	 */
	public function getOptions()
	{
		$optionsJson = $this->getOptionsJson();
		if (empty($optionsJson)){
			return array();
		}
		
		return json_decode($optionsJson, true);
	}
	
	/**
     * Will set and replace existing options on the importer
     * @param array $optionsArray, importer Options
     * @return TBT_Rewards_Model_Importer
	 */
	public function setOptions($optionsArray)
	{
		return $this->setOptionsJson(json_encode($optionsArray));	
	}
	
	/**
	 * Will send out the reprot to the email specified in the importer.
	 * @throws Exception if there was a problem with sending the email or email doesn't exist
	 * @return string success message
	 */
	public function emailReport()
	{	
		$email = $this->getEmail();
		
		if (empty($email)) {
			throw new Exception("No email address available");
		}
		
		$this->_sendEmail($this->_emailSubject, $email, $this->getReport());
		$message = "A summary of this import has been emailed to {$email}.";
		if ($this->_printToStdOut) {
			echo "\n{$message}\n";
		}
	
		return $message;
	}
	
	/**
	 * Send out an email with specified paramaters
	 * @param string $subject
	 * @param string $to
	 * @param string $body
	 */
	protected function _sendEmail($subject, $to, $body)
	{
		$mail = Mage::getModel('core/email');
		$mail->setToEmail($to);
		$mail->setBody($body);
		$mail->setSubject($subject);
		$mail->setType('html');
		
		$fromName = Mage::getStoreConfig('trans_email/ident_general/name');
		if (!empty($fromName)) $mail->setFromEmail($fromName);
		
		$fromEmail = Mage::getStoreConfig('trans_email/ident_general/email');
		if (!empty($fromEmail)) $mail->setFromName($fromEmail);
		
		$mail->send();
	}
	
	/**
	 * Specify if output of this importer should be printed to Standard Out
	 * @param boolean $shouldPrint
	 * @return TBT_RewardsReferral_Model_Import_Referral, this instance
	 */
	public function setPrintToStdOut($shouldPrint)
	{
		if ($this->_isImportFinished) throw new Exception("You should call this method before starting the import.");
		$this->_printToStdOut = $shouldPrint;
		return $this;
	}
	
	/**
     * Make sure we're properly setting all initial importer variables
	 */
	protected function _beforeSave()
	{
		if ($this->isObjectNew()) {
			$this->setCreatedAt($this->_getUtcTimestamp());
		}
		
		if (empty($this->_importerType)){
			throw new Exception("You must specify an importerType in your implementation of this importer");
		}
		$this->setType($this->_importerType);
		
		return parent::_beforeSave();
	}
	
	/**
	 * Will store the arguments for a final report.
	 * If PRINT_TO_STDOUT is on, will also print to screen right away.
	 * @param string $message
	 * @param array $line of CSV file causing the warning
	 * @param int $lineNumber of the original CSV file
	 */
	protected function _reportError ($message, $line = array(), $lineNumber = null)
	{
		$index = is_numeric($lineNumber) ? $lineNumber : count($this->_errors);
		$this->_errors[$index] = array(
				"message" 		=> $message,
				"line"			=> $line,
				"lineNumber"	=> $lineNumber
		);
	
		if ($this->_printToStdOut){
			echo "[ERROR] (LINE #{$lineNumber}):\t {$message}\n";
		}
	}
	
	/**
	* Will store the arguments for a final report.
	* If PRINT_TO_STDOUT is on, will also print to screen right away.
	* @param string $message
	* @param array $line of CSV file causing the warning
	* @param int $lineNumber of the original CSV file
	*/
	protected function _reportSuccess ($message, $line = array(), $lineNumber = null)
	{
		$index = is_numeric($lineNumber) ? $lineNumber : count($this->_successes);
		$this->_successes[$index] = array(
				"message" 		=> $message,
				"line"			=> $line,
				"lineNumber"	=> $lineNumber
		);
	
		if ($this->_printToStdOut){
			echo "[OK]\t{$message}\n";
		}
	}

	/**
	* Will store the arguments for a final report.
	* If PRINT_TO_STDOUT is on, will also print to screen right away.
	* @param string $message
	* @param array $line of CSV file causing the warning
	* @param int $lineNumber of the original CSV file
	*/
	protected function _reportWarning ($message, $line = array(), $lineNumber = null)
	{
		$index = is_numeric($lineNumber) ? $lineNumber : count($this->_warnings);
		$this->_warnings[$index] = array(
			"message" 		=> $message,
			"line"			=> $line,
			"lineNumber"	=> $lineNumber
		);
	
		if ($this->_printToStdOut){
			echo "[WARNING] (LINE #{$lineNumber}):\t {$message}\n";
		}
	}
			
	/**
	 * Get the timestamp in Utc
	 */
	protected function _getUtcTimestamp()
	{
		return Mage::getModel('core/date')->gmtTimestamp($localTimestamp);
	} 
}