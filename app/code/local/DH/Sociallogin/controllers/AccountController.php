<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category    DH
 * @package     DH_Sociallogin
 * @copyright   Copyright (c) 2013 DHZoanku
 * @license     License GNU General Public License version 2 or later; 
 */
class DH_Sociallogin_AccountController extends Mage_Core_Controller_Front_Action {
	/**
     * Action list where need check enabled cookie
     *
     * @var array
     */
    protected $_cookieCheckActions = array('loginPost', 'createpost');
    
	/**
     * Retrieve customer session model object
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }
	
	/**
     * Action predispatch
     *
     * Check customer authentication for some actions
     */
    public function preDispatch()
    {
        // a brute-force protection here would be nice

        parent::preDispatch();

        if (!$this->getRequest()->isDispatched()) {
            return;
        }
    }

    /**
     * Action postdispatch
     *
     * Remove No-referer flag from customer session after each action
     */
    public function postDispatch()
    {
        parent::postDispatch();
        $this->_getSession()->unsNoReferer(false);
    }
    
    /**
     * Login post action
     */
    public function loginPostAction(){
    	$session = $this->_getSession();
   		if ($this->getRequest()->isPost()) {
            $login = $this->getRequest()->getPost('login');
            if (!empty($login['username']) && !empty($login['password'])) {
                try {
                    $login = $session->login($login['username'], $login['password']);
                    $result['success'] = $this->_loginRedirect(true);
        			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                    return ;
                } catch (Mage_Core_Exception $e) {
                    switch ($e->getCode()) {
                        case Mage_Customer_Model_Customer::EXCEPTION_EMAIL_NOT_CONFIRMED:
                            $value = Mage::helper('customer')->getEmailConfirmationUrl($login['username']);
                            $result['error'] = Mage::helper('customer')->__('This account is not confirmed. <a href="%s">Click here</a> to resend confirmation email.', $value);
                            break;
                        case Mage_Customer_Model_Customer::EXCEPTION_INVALID_EMAIL_OR_PASSWORD:
                            $result['error'] = $e->getMessage();
                            break;
                        default:
                            $result['error'] = $e->getMessage();
                    }
                } catch (Exception $e) {
                    
                }
            } else {
            	$result['error'] = Mage::helper('sociallogin')->__('Login and password are required.');
            }
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        }
    }
    
 	/**
     * Define target URL and redirect customer after logging in
     */
    protected function _loginRedirect($return=false)
    {
        $session = $this->_getSession();

        if (!$session->getBeforeAuthUrl() || $session->getBeforeAuthUrl() == Mage::getBaseUrl()) {
            // Set default URL to redirect customer to
            $session->setBeforeAuthUrl(Mage::helper('customer')->getAccountUrl());
            // Redirect customer to the last page visited after logging in
            if ($session->isLoggedIn()) {
                if (!Mage::getStoreConfigFlag(
                    Mage_Customer_Helper_Data::XML_PATH_CUSTOMER_STARTUP_REDIRECT_TO_DASHBOARD
                )) {
                    $referer = $this->getRequest()->getParam(Mage_Customer_Helper_Data::REFERER_QUERY_PARAM_NAME);
                    if ($referer) {
                        // Rebuild referer URL to handle the case when SID was changed
                        $referer = Mage::getModel('core/url')
                            ->getRebuiltUrl(Mage::helper('core')->urlDecode($referer));
                        if ($this->_isUrlInternal($referer)) {
                            $session->setBeforeAuthUrl($referer);
                        }
                    }
                } else if ($session->getAfterAuthUrl()) {
                    $session->setBeforeAuthUrl($session->getAfterAuthUrl(true));
                }
            } else {
                $session->setBeforeAuthUrl(Mage::helper('customer')->getLoginUrl());
            }
        } else if ($session->getBeforeAuthUrl() == Mage::helper('customer')->getLogoutUrl()) {
            $session->setBeforeAuthUrl(Mage::helper('customer')->getDashboardUrl());
        } else {
            if (!$session->getAfterAuthUrl()) {
                $session->setAfterAuthUrl($session->getBeforeAuthUrl());
            }
            if ($session->isLoggedIn()) {
                $session->setBeforeAuthUrl($session->getAfterAuthUrl(true));
            }
        }
        if($return)
        	return $session->getBeforeAuthUrl(true);
        $this->_redirectUrl($session->getBeforeAuthUrl(true));
    }
    
    /**
     * Facebook Login action
     */
	public function fbloginAction(){
    	
    	require_once dirname(dirname(__FILE__)).'/Model/facebook/facebook.php';
    	
    	$fbAppid  = Mage::getStoreConfig('sociallogin/facebook/appid');
		$fbSecret = Mage::getStoreConfig('sociallogin/facebook/secret');
    	$_fbConfig = array(
						'appId'  => $fbAppid,
		  				'secret' => $fbSecret,
						'cookie' => false
					);
		$facebook = new Facebook($_fbConfig);
	
		if ($token=$this->getRequest()->getParam('code')){
			$fbAccount = $facebook->api('/me?access_token='.$token);
			if (!empty($fbAccount)){
				$session = $this->_getSession();
	        	if (!$customer = Mage::registry('current_customer')) {
	               $customer = Mage::getModel('customer/customer')->setId(null);
	            }
	            $customer->setWebsiteId(Mage::getModel('core/store')->load(Mage::app()->getStore()->getStoreId())->getWebsiteId());
	            $user = $customer->loadByEmail($fbAccount['email']);
	            if ($user->getId()){
	            	$session->loginById($user->getId());
					$this->_loginRedirect();
					return ;
	            }else{
	            	$customer->setData('firstname',$fbAccount['first_name']);
			        $customer->setData('lastname',$fbAccount['last_name']);
			        $customer->setData('email',$fbAccount['email']);
			        $customer->setData('password',md5(time().$fbAccount['id']));
			        $customer->setData('is_active', 1);
			        $customer->setData('confirmation',null);
			        $customer->setConfirmation(null);
			        $customer->getGroupId();
			        $customer->save();
			         Mage::getModel('customer/customer')->load($customer->getId())->setConfirmation(null)->save();
			        $customer->setConfirmation(null);
			        $session->setCustomerAsLoggedIn($customer);
			        $url = $this->_welcomeCustomer($customer);
                    $this->_redirectSuccess($url);
                    return;
	            }
				
			}
			
		}else{
			$fbUser = $facebook->getUser();
			$fbLoginParams = array(
				'redirect_uri'=>Mage::getUrl('sociallogin/account/fblogin'),
				'scope'=>'email'
			);
			$this->_redirectUrl($facebook->getLoginUrl($fbLoginParams));
			return ;
		}
    }
    
    /**
     * Google login action
     */
    public function gologinAction(){
    	require_once dirname(dirname(__FILE__)).'/Model/google/Google_Client.php';
    	require_once dirname(dirname(__FILE__)).'/Model/google/contrib/Google_Oauth2Service.php';
    	
    	$goClientId = Mage::getStoreConfig('sociallogin/google/clientid');
		$goClientSecret = Mage::getStoreConfig('sociallogin/google/clientsecret');
		
		$gClient = new Google_Client();
		$gClient->setApplicationName(Mage::app()->getStore()->getName());
		$gClient->setClientId($goClientId);
    	$gClient->setClientSecret($goClientSecret);
    	$gClient->setRedirectUri(Mage::getUrl('sociallogin/account/gologin'));
    	$google_oauthV2 = new Google_Oauth2Service($gClient);
		
    	if ($code = $this->getRequest()->getParam('code')){
    		$gClient->authenticate();
    		if ($gClient->getAccessToken()){
    			$session = $this->_getSession();
    			$_gUser = $google_oauthV2->userinfo->get();
    			
    			if (!$customer = Mage::registry('current_customer')) {
	               $customer = Mage::getModel('customer/customer')->setId(null);
	            }
	            $customer->setWebsiteId(Mage::getModel('core/store')->load(Mage::app()->getStore()->getStoreId())->getWebsiteId());
	            
    			$user = $customer->loadByEmail($_gUser['email']);
    			if ($user->getId()){
                	$session->loginById($user->getId());
                	$this->_loginRedirect();
					return ;
    			}else{
    				$customer->setData('firstname',$_gUser['given_name']);
			        $customer->setData('lastname',$_gUser['family_name']);
			        $customer->setData('email',$_gUser['email']);
			        $customer->setData('password',md5(time().$_gUser['id']));
			        $customer->setData('is_active', 1);
			        $customer->setData('confirmation',null);
			        $customer->setConfirmation(null);
			        $customer->getGroupId();
			        $customer->save();
			         Mage::getModel('customer/customer')->load($customer->getId())->setConfirmation(null)->save();
        			$customer->setConfirmation(null);
        			$session->setCustomerAsLoggedIn($customer);
        			$url = $this->_welcomeCustomer($customer);
                    $this->_redirectSuccess($url);
                    return;
    			}
    		}
    	}else{
    		$this->_redirectUrl($gClient->createAuthUrl());
    	}
    }
    
    /**
     * Twitter login action
     */
    public function twloginAction(){
    	require_once dirname(dirname(__FILE__)).'/Model/twitter/twitteroauth.php';
    	
    	$twCustomerKey =  Mage::getStoreConfig('sociallogin/twitter/customerkey');
		$twCustomerSecret = Mage::getStoreConfig('sociallogin/twitter/custoemrsecret');
		
		$session = $this->_getSession();
		
    	if ($oauth_token =$this->getRequest()->getParam('oauth_token')){
			if ($session->getData('twtoken') !== $oauth_token){
				$session->clear();
			}
			
			$twitter = new TwitterOAuth($twCustomerKey,$twCustomerSecret,$session->getData('twtoken'),$session->getData('twtoken_secret'));
			$access_token = $twitter->getAccessToken($this->getRequest()->getParam('oauth_verifier'));
			
			if($twitter->http_code==200)
			{
				$twUser = $twitter->get('account/verify_credentials');
				$twEmail = $twUser->screen_name.'@twitter.com';
				
    			if (!$customer = Mage::registry('current_customer')) {
	               $customer = Mage::getModel('customer/customer')->setId(null);
	            }
	            $customer->setWebsiteId(Mage::getModel('core/store')->load(Mage::app()->getStore()->getStoreId())->getWebsiteId());
				$user = $customer->loadByEmail($twEmail);
	            if ($user->getId()){
					$session->loginById($user->getId());
					$this->_loginRedirect();
					return ;
				}else{
					$customer->setData('firstname',$twUser->screen_name);
					$customer->setData('lastname',$twUser->name);
					$customer->setData('email',$twEmail);
					$customer->setData('password',md5(time().$twUser->id));
					$customer->setData('is_active', 1);
					$customer->setData('confirmation',null);
					$customer->setConfirmation(null);
					$customer->getGroupId();
					$customer->save();
					
					$session->unsetData('twtoken');
					$session->unsetData('twtoken_secret');
					
					Mage::getModel('customer/customer')->load($customer->getId())->setConfirmation(null)->save();
					$customer->setConfirmation(null);
					$session->setCustomerAsLoggedIn($customer);
					$session->addSuccess(Mage::helper('sociallogin')->__('Account Api Twitter not using Email. You can change it'));
					$url = $this->_welcomeCustomer($customer);
                    $this->_redirectSuccess($url);
                    return ;
				}
			}else{
				$this->getResponse()->setBody(Mage::helper('sociallogin')->__('Error connecting to Twitter! try again later!'));
			}
		}else{ 
			$twitter = new TwitterOAuth($twCustomerKey,$twCustomerSecret);
			$callback = Mage::getUrl('sociallogin/account/twlogin');
			$request_token = $twitter->getRequestToken($callback);
			
			$session->setData('twtoken',$request_token['oauth_token']);
			$session->setData('twtoken_secret',$request_token['oauth_token_secret']);
			
			if ($twitter->http_code==200){
				$twUrl = $twitter->getAuthorizeURL($request_token['oauth_token']);
				$this->_redirectUrl($twUrl);
			}else{
				$this->getResponse()->setBody(Mage::helper('sociallogin')->__('Error connecting to Twitter! try again later!'));
			}
		}
    }
    
    /**
     * Yahoo login action
     */
    public function yaloginAction(){
    	require_once dirname(dirname(__FILE__)).'/Model/yahoo/Yahoo.inc';
    	
    	
    	$yahoo_consumer_key = Mage::getStoreConfig('sociallogin/yahoo/customerkey');
		$yahoo_consumer_secret = Mage::getStoreConfig('sociallogin/yahoo/custoemrsecret');
		$yahoo_app_id = Mage::getStoreConfig('sociallogin/yahoo/appid');
		
    	$hasSession = YahooSession::hasSession($yahoo_consumer_key, $yahoo_consumer_secret,$yahoo_app_id);

		if ($hasSession==FALSE){
			
			$callback = Mage::getUrl('sociallogin/account/yahoo');
			
			$yahoo_auth_url = YahooSession::createAuthorizationUrl($yahoo_consumer_key,$yahoo_consumer_secret,$callback);
			if ($yahoo_auth_url){
				$this->_redirectUrl($yahoo_auth_url);
			}
			
		}else{
			$yahoo_session = YahooSession::requireSession($yahoo_consumer_key,$yahoo_consumer_secret,$yahoo_app_id);
			if ($yahoo_session){
				$yahoo_user = $yahoo_session->getSessionedUser();
				$yahoo_profile = $yahoo_user->getProfile();
				
				if (!$customer = Mage::registry('current_customer')) {
	               $customer = Mage::getModel('customer/customer')->setId(null);
	            }
	            $customer->setWebsiteId(Mage::getModel('core/store')->load(Mage::app()->getStore()->getStoreId())->getWebsiteId());
				
	            $user = $customer->loadByEmail($yahoo_profile->emails[0]->handle);
	            if ($user->getId()){
					$session->loginById($user->getId());
					$this->_loginRedirect();
					return ;
				}else{
					$customer->setData('firstname',$yahoo_profile->familyName);
					$customer->setData('lastname',$yahoo_profile->givenName);
					$customer->setData('email',$yahoo_profile->emails[0]->handle);
					$customer->setData('password',md5(time()));
					$customer->setData('is_active', 1);
					$customer->setData('confirmation',null);
					$customer->setConfirmation(null);
					$customer->getGroupId();
					$customer->save();
					
					YahooSession::clearSession();
					
					Mage::getModel('customer/customer')->load($customer->getId())->setConfirmation(null)->save();
					$customer->setConfirmation(null);
					$session->setCustomerAsLoggedIn($customer);
					$url = $this->_welcomeCustomer($customer);
                    $this->_redirectSuccess($url);
                    return ;
				}
			}
		}
		
    }
	
    /**
     * For got password  action
     */
     public function forgotPasswordPostAction(){
    	$email = (string) $this->getRequest()->getPost('email');
    	$result = array();
    	if ($email){
    		if (!Zend_Validate::is($email, 'EmailAddress')) {
    			$result['error'] = Mage::helper('sociallogin')->__('Invalid email address.');
                $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
            }
             /** @var $customer Mage_Customer_Model_Customer */
            $customer = Mage::getModel('customer/customer')
                ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
                ->loadByEmail($email);
             if ($customer->getId()) {
            	 try {
                    $newResetPasswordLinkToken = Mage::helper('customer')->generateResetPasswordLinkToken();
                    $customer->changeResetPasswordLinkToken($newResetPasswordLinkToken);
                    $customer->sendPasswordResetConfirmationEmail();
                } catch (Exception $exception) {
                	  $result['error'] = $exception->getMessage();
                	  $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                }
             }
             $result['success'] = Mage::helper('customer')->__('If there is an account associated with %s you will receive an email with a link to reset your password.', Mage::helper('customer')->htmlEscape($email));
    		 $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    	}else{
    		$result['error'] = Mage::helper('sociallogin')->__('Please enter your email.');
    		$this->getResponse()->setBody($result);
    	}
    }
    
    /**
     * Create post action
     */
 	public function createPostAction(){
    	$session = $this->_getSession();
    	$session->setEscapeMessages(true); // prevent XSS injection in user input
    	if ($this->getRequest()->isPost()){
    		$errors = array();
    		$result = array();
    		$firstname = $this->getRequest()->getPost('firstname',false);
    		$lastname = $this->getRequest()->getPost('lastname',false);
    		$email = $this->getRequest()->getPost('email',false);
    		$password = $this->getRequest()->getPost('password',false);
    		$confirmation= $this->getRequest()->getPost('confirmation',false);
    		if (!$customer = Mage::registry('current_customer')) {
                $customer = Mage::getModel('customer/customer')->setId(null);
            }
            try {
	            $customer->setData('firstname',$firstname);
		        $customer->setData('lastname',$lastname);
		        $customer->setData('email',$email);
		        $customer->setData('password',$password);
		        $customer->setData('is_active', 1);
		        $customer->setData('confirmation',$confirmation);
		        $customer->setConfirmation($confirmation);
		        $customer->getGroupId();
		        $customerErrors = $customer->validate();
	    		if (is_array($customerErrors)) {
	                    $errors['error'] = array_merge($customerErrors, $errors);
	                }
	                 $validationResult = count($errors) == 0;
	                 if (true === $validationResult) {
			        $customer->save();
			        Mage::getModel('customer/customer')->load($customer->getId())->setConfirmation(null)->save();
	        		$customer->setConfirmation(null);
	        		$session->setCustomerAsLoggedIn($customer);
	        		$result['success'] = $this->_welcomeCustomer($customer);//Mage::helper('customer')->getDashboardUrl();
	        		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
	        		return;
	        	}else{
	        		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($errors));
	        	}
        	
            }catch (Mage_Core_Exception $e){
            	if ($e->getCode() === Mage_Customer_Model_Customer::EXCEPTION_EMAIL_EXISTS) {
            		$msg = array(Mage::helper('sociallogin')->__('There is already an account with this email address'));
            		$errors['error'] = array_merge($msg,$errors);
            		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($errors));
            	}
            
    		}catch (Exception $e){
            	
            }
    	}
    }
    
	/**
     * Add welcome message and send new account email.
     * Returns success URL
     *
     * @param Mage_Customer_Model_Customer $customer
     * @param bool $isJustConfirmed
     * @return string
     */
    protected function _welcomeCustomer(Mage_Customer_Model_Customer $customer, $isJustConfirmed = false)
    {
        $this->_getSession()->addSuccess(
            Mage::helper('sociallogin')->__('Thank you for registering with %s.', Mage::app()->getStore()->getFrontendName())
        );
        if ($this->_isVatValidationEnabled()) {
            // Show corresponding VAT message to customer
            $configAddressType = Mage::helper('customer/address')->getTaxCalculationAddressType();
            $userPrompt = '';
            switch ($configAddressType) {
                case Mage_Customer_Model_Address_Abstract::TYPE_SHIPPING:
                    $userPrompt = Mage::helper('sociallogin')->__('If you are a registered VAT customer, please click <a href="%s">here</a> to enter you shipping address for proper VAT calculation', Mage::getUrl('customer/address/edit'));
                    break;
                default:
                    $userPrompt = Mage::helper('sociallogin')->__('If you are a registered VAT customer, please click <a href="%s">here</a> to enter you billing address for proper VAT calculation', Mage::getUrl('customer/address/edit'));
            }
            $this->_getSession()->addSuccess($userPrompt);
        }

        $customer->sendNewAccountEmail(
            $isJustConfirmed ? 'confirmed' : 'registered',
            '',
            Mage::app()->getStore()->getId()
        );

        $successUrl = Mage::getUrl('customer/account/index', array('_secure'=>true));
        if ($this->_getSession()->getBeforeAuthUrl()) {
            $successUrl = $this->_getSession()->getBeforeAuthUrl(true);
        }
        return $successUrl;
    }
    
	/**
     * Check whether VAT ID validation is enabled
     *
     * @param Mage_Core_Model_Store|string|int $store
     * @return bool
     */
    protected function _isVatValidationEnabled($store = null)
    {
        return Mage::helper('customer/address')->isVatValidationEnabled($store);
    }
}