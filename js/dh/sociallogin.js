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
var DHModal;
function doCreateAccount(){
	DHModal.center();
	  $('dhsc_create').show();
	  $('dhsc_forgot').hide();
	  $('dhsc_login').hide();
}
function doSendEmail(){
	DHModal.center();
	$('dhsc_forgot').toggle();
}
function doLogin(){
	DHModal.center();
	$('dhsc_create').hide();
	$('dhsc_login').show();
	$('dhsc_forgot').hide();
}

function socialloginModal(Id)
{
	DHModal = new Lightbox(Id);
	DHModal.open();
	return false;
}
function socialLoginClose(){
	if(DHModal)
		DHModal.close();
	return false;
}
