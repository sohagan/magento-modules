<?php

if (!defined('COMPILER_INCLUDE_PATH')) {
    include_once ("Common/ThePaymentGateway/PaymentSystem.php");
	include_once ("Common/PaymentFormHelper.php");
	include_once ("Common/ISOCurrencies.php");
	include_once ("Common/ISOCountries.php");
} else {
    include_once ("Cardsave_Cardsaveonlinepayments_Model_Common_ThePaymentGateway_PaymentSystem.php");
	include_once ("Cardsave_Cardsaveonlinepayments_Model_Common_PaymentFormHelper.php");
	include_once ("Cardsave_Cardsaveonlinepayments_Model_Common_ISOCurrencies.php");
	include_once ("Cardsave_Cardsaveonlinepayments_Model_Common_ISOCountries.php");
}

class Cardsave_Cardsaveonlinepayments_Model_Direct extends Mage_Payment_Model_Method_Abstract
{
	/**
  	* unique internal payment method identifier
  	*
  	* @var string [a-z0-9_]
  	*/
	protected $_code = 'cardsaveonlinepayments';
 	protected $_formBlockType = 'cardsaveonlinepayments/form'; 
 	protected $_infoBlockType = 'cardsaveonlinepayments/info';

	protected $_isGateway = true;
	protected $_canAuthorize = true;
	protected $_canCapture = true;
	protected $_canCapturePartial = false;
	protected $_canRefund = true;
	protected $_canRefundInvoicePartial = true;
	protected $_canVoid = false;
	protected $_canUseInternal = false;
	protected $_canUseCheckout = true;
	protected $_canUseForMultishipping = true;
	protected $_canSaveCc = false;
	
	/**
	* Assign data to info model instance 
	*  
	* @param   mixed $data 
	* @return  Mage_Payment_Model_Info 
	*/  
 	public function assignData($data)  
	{
	    if (!($data instanceof Varien_Object))
	    {
	        $data = new Varien_Object($data);
	    }
	    
	    $info = $this->getInfoInstance();
	    
	    $info->setCcOwner($data->getCcOwner())
	        ->setCcLast4(substr($data->getCcNumber(), -4))
	        ->setCcNumber($data->getCcNumber())
	        ->setCcCid($data->getCcCid())
	        ->setCcExpMonth($data->getCcExpMonth())
	        ->setCcExpYear($data->getCcExpYear())
            ->setCcSsStartMonth($data->getCcSsStartMonth())
            ->setCcSsStartYear($data->getCcSsStartYear())
            ->setCcSsIssue($data->getCcSsIssue());

	    return $this;
	}
	
	/**
     * Validate payment method information object
     *
     * @param   Mage_Payment_Model_Info $info
     * @return  Mage_Payment_Model_Abstract
     */
	public function validate()
	{
		// NOTE : cancel out the core Magento validator functionality, the payment gateway will overtake this task
		
		return $this;
	}
	
	/**
     * Authorize - core Mage pre-authorization functionality
     *
     * @param   Varien_Object $orderPayment
     * @return  Mage_Payment_Model_Abstract
     */
	public function authorize(Varien_Object $payment, $amount)
	{
		$error = false;
		$mode = $this->getConfigData('mode');
		$nVersion = $this->getVersion();
				
		//Mage::throwException('This payment module only allow capture payments.');
		
		// TODO : need to finish for non Direct API methods
		switch ($mode)
		{
			case Cardsave_Cardsaveonlinepayments_Model_Source_PaymentMode::PAYMENT_MODE_DIRECT_API:
		  		$error = $this->_runTransaction($payment, $amount);
		  		break;
		  	case Cardsave_Cardsaveonlinepayments_Model_Source_PaymentMode::PAYMENT_MODE_HOSTED_PAYMENT_FORM:
		  		$error = $this->_runHostedPaymentTransaction($payment, $amount);
		  		break;
		  	case Cardsave_Cardsaveonlinepayments_Model_Source_PaymentMode::PAYMENT_MODE_TRANSPARENT_REDIRECT:
		  		$error = $this->_runTransparentRedirectTransaction($payment, $amount);
		  		//Mage::throwException('TR not supported');
		  		break;
		  	default:
		  		Mage::throwException('Invalid payment type: '.$this->getConfigData('mode'));
		  		break;
		}
		
		if($error)
		{
			Mage::throwException($error);
		}
		
		return $this;
	}
	
	/**
     * Capture payment - immediate settlement payments
     *
     * @param   Varien_Object $payment
     * @return  Mage_Payment_Model_Abstract
     */
	public function capture(Varien_Object $payment, $amount)
	{
		$error = false;
		$session = Mage::getSingleton('checkout/session');
		$mode = $this->getConfigData('mode');
		$nVersion = $this->getVersion();
		
		if($amount <= 0)
		{
			Mage::throwException(Mage::helper('paygate')->__('Invalid amount for authorization.'));
		}
		else
		{
			if($session->getThreedsecurerequired())
			{
				$md = $session->getMd();
				$pares = $session->getPares();
				
				$session->setThreedsecurerequired(null);
				$this->_run3DSecureTransaction($payment, $pares, $md);
				
				return $this;
			}
			if($session->getRedirectedpayment())
			{
				$szStatusCode = $session->getStatuscode();
				$szMessage = $session->getMessage();
				$szPreviousStatusCode = $session->getPreviousstatuscode();
				$szPreviousMessage = $session->getPreviousmessage();
				$szOrderID = $session->getOrderid();
				// check whether it is a hosted payment or a transparent redirect action
				$boIsHostedPaymentAction = $session->getIshostedpayment();
				
				$session->setRedirectedpayment(null);
				$session->setIshostedpayment(null);
				$this->_runRedirectedPaymentComplete($payment, $boIsHostedPaymentAction, $szStatusCode, $szMessage, $szPreviousStatusCode, $szPreviousMessage, $szOrderID);
				
				return $this;
			}
			
			if($session->getIsCollectionCrossReferenceTransaction())
			{
				// do a CrossReference transaction
				$error = $this->_runCrossReferenceTransaction($payment, "COLLECTION", $amount);
			}
			else 
			{
				// fresh payment request
				$session->setThreedsecurerequired(null)
						->setRedirectedpayment(null)
						->setIshostedpayment(null)
						->setHostedPayment(null)
						->setMd(null)
						->setPareq(null)
						->setAcsurl(null)
						->setPaymentprocessorresponse(null);
				
				$payment->setAmount($amount);
				
			  	switch ($mode)
			  	{
			  		case Cardsave_Cardsaveonlinepayments_Model_Source_PaymentMode::PAYMENT_MODE_DIRECT_API:
			  			$error = $this->_runTransaction($payment, $amount);
			  			break;
			  		case Cardsave_Cardsaveonlinepayments_Model_Source_PaymentMode::PAYMENT_MODE_HOSTED_PAYMENT_FORM:
			  			$error = $this->_runHostedPaymentTransaction($payment, $amount);
			  			break;
			  		case Cardsave_Cardsaveonlinepayments_Model_Source_PaymentMode::PAYMENT_MODE_TRANSPARENT_REDIRECT:
			  			$error = $this->_runTransparentRedirectTransaction($payment, $amount);
			  			break;
			  		default:
			  			Mage::throwException('Invalid payment type: '.$this->getConfigData('mode'));
			  			break;
			  	}
			}
		}
		
		if($error)
		{
			Mage::throwException($error);
		}
		
		return $this;
	}
	
	/**
	 * Processing the transaction using the direct integration
	 * 
	 * @param   Varien_Object $orderPayment
	 * @param   $amount
	 * @return  void
	 */
	public function _runTransaction(Varien_Object $payment, $amount)
	{
		$takePaymentInStoreBaseCurrency = $this->getConfigData('takePaymentInStoreBaseCurrency');
		
		$error = '';
		$session = Mage::getSingleton('checkout/session');
		$nVersion = $this->getVersion();
		
		$MerchantID = $this->getConfigData('merchantid');
		$Password = $this->getConfigData('password');
		$SecretKey = $this->getConfigData('secretkey');
		// assign payment form field values to variables
		$order = $payment->getOrder();
		$szOrderID = $payment->getOrder()->increment_id;
		$szOrderDescription = '';
		$szCardName = $payment->getCcOwner();
		$szCardNumber = $payment->getCcNumber();
		$szIssueNumber = $payment->getCcSsIssue();
		$szCV2 = $payment->getCcCid();
		$nCurrencyCode;
		$szCurrencyShort = $order->getOrderCurrency()->getCurrencyCode();
		// address details
		$billingAddress = $order->getBillingAddress();
		$szAddress1 = $billingAddress->getStreet1();
		$szAddress2 = $billingAddress->getStreet2();
		$szAddress3 = $billingAddress->getStreet3();
		$szAddress4 = $billingAddress->getStreet4();
		$szCity = $billingAddress->getCity();
		$szState = $billingAddress->getRegion();
		$szPostCode = $billingAddress->getPostcode();
		$szISO2CountryCode = $billingAddress->getCountry();
		$nCountryCode;
		$szEmailAddress = $billingAddress->getCustomerEmail();
		$szPhoneNumber = $billingAddress->getTelephone();
		$nDecimalAmount;
		$szTransactionType;
		
		$PaymentProcessorFullDomain = $this->_getPaymentProcessorFullDomain();
		$iclISOCurrencyList = CSV_ISOCurrencies::getISOCurrencyList();
		$iclISOCountryList = CSV_ISOCountries::getISOCountryList();
		
		$rgeplRequestGatewayEntryPointList = new CSV_RequestGatewayEntryPointList();
		$rgeplRequestGatewayEntryPointList->add("https://gw1.".$PaymentProcessorFullDomain, 100, 2);
		$rgeplRequestGatewayEntryPointList->add("https://gw2.".$PaymentProcessorFullDomain, 200, 2);
		$rgeplRequestGatewayEntryPointList->add("https://gw3.".$PaymentProcessorFullDomain, 300, 2);
		
		$paymentAction = $this->getConfigData('payment_action');
		if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE_CAPTURE)
		{
			$szTransactionType = "SALE";
		}
		else if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE)
		{
			$szTransactionType = "PREAUTH";
		}
		else 
		{
			Mage::throwException('Unknown payment action: '.$paymentAction);
		}
		
		$cdtCardDetailsTransaction = new CSV_CardDetailsTransaction($rgeplRequestGatewayEntryPointList);

		$cdtCardDetailsTransaction->getMerchantAuthentication()->setMerchantID($MerchantID);
		$cdtCardDetailsTransaction->getMerchantAuthentication()->setPassword($Password);

		$cdtCardDetailsTransaction->getTransactionDetails()->getMessageDetails()->setTransactionType($szTransactionType);

		if (!$takePaymentInStoreBaseCurrency) {	
			// Take payment in order currency
			$szCurrencyShort = $order->getOrderCurrency()->getCurrencyCode();
			if ($szCurrencyShort != '' && $iclISOCurrencyList->getISOCurrency($szCurrencyShort, $icISOCurrency))
			{
				$nCurrencyCode = $icISOCurrency->getISOCode();
				$cdtCardDetailsTransaction->getTransactionDetails()->getCurrencyCode()->setValue($icISOCurrency->getISOCode());
			}
			
			// Calculate amount
			$power = pow(10, $icISOCurrency->getExponent());
			$nAmount = round($order->getGrandTotal() * $power,0);			
		} else {
			// Take payment in site base currency
			//$szCurrencyShort = $order->getOrderCurrency()->getCurrencyCode();
			$szCurrencyShort = $order->getBaseCurrencyCode();
			if ($szCurrencyShort != '' && $iclISOCurrencyList->getISOCurrency($szCurrencyShort, $icISOCurrency))
			{
				$nCurrencyCode = $icISOCurrency->getISOCode();
				$cdtCardDetailsTransaction->getTransactionDetails()->getCurrencyCode()->setValue($icISOCurrency->getISOCode());
			}
			
			// Calculate amount
			$nAmount = $this->_getRoundedAmount($amount, $icISOCurrency->getExponent());			
		}
				
		$cdtCardDetailsTransaction->getTransactionDetails()->getAmount()->setValue($nAmount);
	
		$cdtCardDetailsTransaction->getTransactionDetails()->setOrderID($szOrderID);
		$cdtCardDetailsTransaction->getTransactionDetails()->setOrderDescription($szOrderDescription);
	
		$cdtCardDetailsTransaction->getTransactionDetails()->getTransactionControl()->getEchoCardType()->setValue(true);
		$cdtCardDetailsTransaction->getTransactionDetails()->getTransactionControl()->getEchoAmountReceived()->setValue(true);
		$cdtCardDetailsTransaction->getTransactionDetails()->getTransactionControl()->getEchoAVSCheckResult()->setValue(true);
		$cdtCardDetailsTransaction->getTransactionDetails()->getTransactionControl()->getEchoCV2CheckResult()->setValue(true);
		$cdtCardDetailsTransaction->getTransactionDetails()->getTransactionControl()->getThreeDSecureOverridePolicy()->setValue(true);
		$cdtCardDetailsTransaction->getTransactionDetails()->getTransactionControl()->getDuplicateDelay()->setValue(60);
	
		$cdtCardDetailsTransaction->getTransactionDetails()->getThreeDSecureBrowserDetails()->getDeviceCategory()->setValue(0);
		$cdtCardDetailsTransaction->getTransactionDetails()->getThreeDSecureBrowserDetails()->setAcceptHeaders("*/*");
		$cdtCardDetailsTransaction->getTransactionDetails()->getThreeDSecureBrowserDetails()->setUserAgent($_SERVER["HTTP_USER_AGENT"]);
	
		$cdtCardDetailsTransaction->getCardDetails()->setCardName($szCardName);
		$cdtCardDetailsTransaction->getCardDetails()->setCardNumber($szCardNumber);
	
		if ($payment->getCcExpMonth() != "")
		{
			$cdtCardDetailsTransaction->getCardDetails()->getExpiryDate()->getMonth()->setValue($payment->getCcExpMonth());
		}
		if ($payment->getCcExpYear() != "")
		{
			$cdtCardDetailsTransaction->getCardDetails()->getExpiryDate()->getYear()->setValue($payment->getCcExpYear());
		}
		if ($payment->getCcSsStartMonth() != "")
		{
			$cdtCardDetailsTransaction->getCardDetails()->getStartDate()->getMonth()->setValue($payment->getCcSsStartMonth());
		}
		if ($payment->getCcSsStartYear() != "")
		{
			$cdtCardDetailsTransaction->getCardDetails()->getStartDate()->getYear()->setValue($payment->getCcSsStartYear());
		}
	
		$cdtCardDetailsTransaction->getCardDetails()->setIssueNumber($szIssueNumber);
		$cdtCardDetailsTransaction->getCardDetails()->setCV2($szCV2);
	
		$cdtCardDetailsTransaction->getCustomerDetails()->getBillingAddress()->setAddress1($szAddress1);
		$cdtCardDetailsTransaction->getCustomerDetails()->getBillingAddress()->setAddress2($szAddress2);
		$cdtCardDetailsTransaction->getCustomerDetails()->getBillingAddress()->setAddress3($szAddress3);
		$cdtCardDetailsTransaction->getCustomerDetails()->getBillingAddress()->setAddress4($szAddress4);
		$cdtCardDetailsTransaction->getCustomerDetails()->getBillingAddress()->setCity($szCity);
		$cdtCardDetailsTransaction->getCustomerDetails()->getBillingAddress()->setState($szState);
		$cdtCardDetailsTransaction->getCustomerDetails()->getBillingAddress()->setPostCode($szPostCode);
	
		$szCountryShort = $this->_getISO3Code($szISO2CountryCode);
		if ($iclISOCountryList->getISOCountry($szCountryShort, $icISOCountry))
		{
			$cdtCardDetailsTransaction->getCustomerDetails()->getBillingAddress()->getCountryCode()->setValue($icISOCountry->getISOCode());
		}
	
		$cdtCardDetailsTransaction->getCustomerDetails()->setEmailAddress($szEmailAddress);
		$cdtCardDetailsTransaction->getCustomerDetails()->setPhoneNumber($szPhoneNumber);
		
		$boTransactionProcessed = $cdtCardDetailsTransaction->processTransaction($cdtrCardDetailsTransactionResult, $todTransactionOutputData);
		
		if ($boTransactionProcessed == false)
		{
			// could not communicate with the payment gateway
			$error = Cardsave_Cardsaveonlinepayments_Model_Common_GlobalErrors::ERROR_261;
			if($cdtCardDetailsTransaction->getLastException())
			{
				$error.= " [ ". $cdtCardDetailsTransaction->getLastException() . " ]";
			}
			
			$szLogMessage = "Couldn't complete transaction. Details: ".print_r($cdtrCardDetailsTransactionResult, 1)." ".print_r($todTransactionOutputData, 1);  //"Couldn't communicate with payment gateway.";
			Mage::log($szLogMessage);
			Mage::log("Last exception: ".print_r($cdtCardDetailsTransaction->getLastException(), 1));
		}
		else
		{
			$szLogMessage = "Transaction could not be completed for OrderID: ".$szOrderID.". Result details: ";
			$szNotificationMessage = 'Payment Processor Response: '.$cdtrCardDetailsTransactionResult->getMessage();
			$szCrossReference = $todTransactionOutputData->getCrossReference();
			
			/* serve out the CrossReference as the TransactionId - this need to be done to enable the "Refund" button 
			   in the Magento CreditMemo internal refund mechanism */
			$payment->setTransactionId($szCrossReference);
			
			switch ($cdtrCardDetailsTransactionResult->getStatusCode())
			{
				case 0:
					// status code of 0 - means transaction successful
					$szLogMessage = "Transaction successfully completed for OrderID: ".$szOrderID.". Response object: ";
					
					// serve out the CrossReference as a TransactionId in the Magento system
					$order->setCustomerNote($szNotificationMessage);
					$this->setPaymentAdditionalInformation($payment, $szCrossReference);
					
					// deactivate the current quote - fixing the cart not emptied bug 
					Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
					
					// add the success message
					Mage::getSingleton('core/session')->addSuccess($szNotificationMessage);
					break;
				case 3:
					// status code of 3 - means 3D Secure authentication required
					$szLogMessage = "3D Secure Authentication required for OrderID: ".$szOrderID.". Response object: ";
					$szNotificationMessage = '';
					
					$szPaReq = $todTransactionOutputData->getThreeDSecureOutputData()->getPaREQ();
					$szACSURL = $todTransactionOutputData->getThreeDSecureOutputData()->getACSURL();
					
					Mage::getSingleton('checkout/session')->setMd($szCrossReference)
	        												->setAcsurl($szACSURL)
			  		   										->setPareq($szPaReq);
					
					Mage::getSingleton('checkout/session')->setRedirectionmethod('_run3DSecureTransaction');
					$order->setIsThreeDSecurePending(true);
					
					break;
				case 5:
					// status code of 5 - means transaction declined
					$error = $szNotificationMessage;
					break;
				case 20:
					// status code of 20 - means duplicate transaction
					$szPreviousTransactionMessage = $cdtrCardDetailsTransactionResult->getPreviousTransactionResult()->getMessage();
					$szLogMessage = "Duplicate transaction for OrderID: ".$szOrderID.". A duplicate transaction means that a transaction with these details has already been processed by the payment provider. The details of the original transaction: ".$szPreviousTransactionMessage.". Response object: ";
					$szNotificationMessage = $szNotificationMessage.". A duplicate transaction means that a transaction with these details has already been processed by the payment provider. The details of the original transaction - Previous Transaction Response: ".$szPreviousTransactionMessage;
					
					if ($cdtrCardDetailsTransactionResult->getPreviousTransactionResult()->getStatusCode()->getValue() != 0)
					{
						$error = $szNotificationMessage;
					}
					else
					{
						Mage::getSingleton('core/session')->addSuccess($szNotificationMessage);
					}
					break;
				case 30:
					// status code of 30 - means an error occurred
					$error = $szNotificationMessage;
					$szLogMessage = "Transaction could not be completed for OrderID: ".$szOrderID.". Error message: ".$cdtrCardDetailsTransactionResult->getMessage();
					if ($cdtrCardDetailsTransactionResult->getErrorMessages()->getCount() > 0)
					{
						$szLogMessage = $szLogMessage.".";
	
						for ($LoopIndex = 0; $LoopIndex < $cdtrCardDetailsTransactionResult->getErrorMessages()->getCount(); $LoopIndex++)
						{
							$szLogMessage = $szLogMessage.$cdtrCardDetailsTransactionResult->getErrorMessages()->getAt($LoopIndex).";";
						}
						$szLogMessage = $szLogMessage." ";
					}
					$szLogMessage = $szLogMessage.' Response object: ';
					break;
				default:
					// unhandled status code
					$error = $szNotificationMessage;
					break;
			}
			
			$szLogMessage = $szLogMessage.print_r($cdtrCardDetailsTransactionResult, 1);
			Mage::log($szLogMessage);
		}
		
		if($error)
		{
			$payment->setStatus('FAIL')
					->setCcApproval('FAIL');
		}

		return $error;
	}
	
	/**
	 * Processing the transaction using the hosted payment form integration 
	 *
	 * @param Varien_Object $payment
	 * @param unknown_type $amount
	 */
	public function _runHostedPaymentTransaction(Varien_Object $payment, $amount)
	{
		$takePaymentInStoreBaseCurrency = $this->getConfigData('takePaymentInStoreBaseCurrency');
		
		$session = Mage::getSingleton('checkout/session');
		$nVersion = $this->getVersion();
		
		$szMerchantID = $this->getConfigData('merchantid');
		$szPassword = $this->getConfigData('password');
		$szPreSharedKey = $this->getConfigData('presharedkey');
		$hmHashMethod = $this->getConfigData('hashmethod');
		$boCV2Mandatory = 'false';
		$boAddress1Mandatory = 'false';
		$boCityMandatory = 'false';
		$boPostCodeMandatory = 'false';
		$boStateMandatory = 'false';
		$boCountryMandatory = 'false';
		$rdmResultdeliveryMethod = $this->getConfigData('resultdeliverymethod');
		$szServerResultURL = '';
		// set to always true to display the result on the Hosted Payment Form
		$boPaymentFormDisplaysResult = '';
		
		switch($rdmResultdeliveryMethod)
		{
			case Cardsave_Cardsaveonlinepayments_Model_Source_ResultDeliveryMethod::RESULT_DELIVERY_METHOD_POST:
				$szCallbackURL = Mage::getUrl('cardsaveonlinepayments/payment/callbackhostedpayment', array('_secure' => true));
				break;
			case Cardsave_Cardsaveonlinepayments_Model_Source_ResultDeliveryMethod::RESULT_DELIVERY_METHOD_SERVER:
				$szCallbackURL = Mage::getUrl('cardsaveonlinepayments/payment/callbackhostedpayment', array('_secure' => true));
				$szServerResultURL = Mage::getUrl('cardsaveonlinepayments/payment/serverresult', array('_secure' => true));
				$boPaymentFormDisplaysResult = 'true';
				break;
			case Cardsave_Cardsaveonlinepayments_Model_Source_ResultDeliveryMethod::RESULT_DELIVERY_METHOD_SERVER_PULL:
				$szCallbackURL = Mage::getUrl('cardsaveonlinepayments/payment/serverpullresult', array('_secure' => true));
				break;
		}
		
		$order = $payment->getOrder();
		$billingAddress = $order->getBillingAddress();
		$iclISOCurrencyList = CSV_ISOCurrencies::getISOCurrencyList();
		$iclISOCountryList = CSV_ISOCountries::getISOCountryList();
		$cookie = Mage::getSingleton('core/cookie');
		$arCookieArray = $cookie->get();
		$arCookieKeysArray = array_keys($arCookieArray);
		$nKeysArrayLength = count($arCookieKeysArray);
		$szCookiePath = $cookie->getPath();
		$szCookieDomain = $cookie->getDomain();
		$szServerResultURLCookieVariables = '';
		$szServerResultURLFormVariables = '';
		$szServerResultURLQueryStringVariables = '';
		//ServerResutlURLCookieVariables string format: cookie1=123&path=/&domain=www.domain.com@@cookie2=456&path=/&domain=www.domain.com 
		
		for($nCount = 0; $nCount < $nKeysArrayLength; $nCount++)
		{
			$szEncodedCookieValue = urlencode($arCookieArray[$arCookieKeysArray[$nCount]]);
			$szServerResultURLCookieVariables .= $arCookieKeysArray[$nCount]."=".$szEncodedCookieValue."&path=".$szCookiePath."&domain=".$szCookieDomain;
			if($nCount < $nKeysArrayLength - 1)
			{
				$szServerResultURLCookieVariables .= "@@";
			}
		}
				
		if (!$takePaymentInStoreBaseCurrency) {	
			// Take payment in order currency
			$szCurrencyShort = $order->getOrderCurrency()->getCurrencyCode();
			if ($szCurrencyShort != '' && $iclISOCurrencyList->getISOCurrency($szCurrencyShort, $icISOCurrency))
			{
				$nCurrencyCode = $icISOCurrency->getISOCode();
			}
			
			// Calculate amount
			$power = pow(10, $icISOCurrency->getExponent());
			$nAmount = round($order->getGrandTotal() * $power,0);			
		} else {
			// Take payment in site base currency
			//$szCurrencyShort = $order->getOrderCurrency()->getCurrencyCode();
			$szCurrencyShort = $order->getBaseCurrencyCode();
			if ($szCurrencyShort != '' && $iclISOCurrencyList->getISOCurrency($szCurrencyShort, $icISOCurrency))
			{
				$nCurrencyCode = $icISOCurrency->getISOCode();
			}
			
			// Calculate amount
			$nAmount = $this->_getRoundedAmount($amount, $icISOCurrency->getExponent());			
		}
				
		$szISO2CountryCode = $billingAddress->getCountry();
		$szCountryShort = $this->_getISO3Code($szISO2CountryCode);
		if($iclISOCountryList->getISOCountry($szCountryShort, $icISOCountry))
		{
			$nCountryCode = $icISOCountry->getISOCode();
		}
		
		$szOrderID = $payment->getOrder()->increment_id;
		//date time with 2008-12-01 14:12:00 +01:00 format
		$szTransactionDateTime = date('Y-m-d H:i:s P');
		$szOrderDescription = '';

		//$szTransactionType = "SALE";
		$paymentAction = $this->getConfigData('payment_action');
		if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE_CAPTURE)
		{
			$szTransactionType = "SALE";
		}
		else if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE)
		{
			$szTransactionType = "PREAUTH";
		}
		else 
		{
			Mage::throwException('Unknown payment action: '.$paymentAction);
		}
		
		$szCustomerName = $billingAddress->getfirstname();
		if($billingAddress->getfirstname())
		{
			$szCustomerName = $szCustomerName.' '.$billingAddress->getlastname();
		}
		$szAddress1 = $billingAddress->getStreet1();
		$szAddress2 = $billingAddress->getStreet2();
		$szAddress3 = $billingAddress->getStreet3();
		$szAddress4 = $billingAddress->getStreet4();
		$szCity = $billingAddress->getCity();
		$szState = $billingAddress->getRegion();
		$szPostCode = $billingAddress->getPostcode();
		
		if($this->getConfigData('cv2mandatory'))
		{
			$boCV2Mandatory = 'true';
		}
		if($this->getConfigData('address1mandatory'))
		{
			$boAddress1Mandatory = 'true';
		}
		if($this->getConfigData('citymandatory'))
		{
			$boCityMandatory = 'true';
		}
		if($this->getConfigData('postcodemandatory'))
		{
			$boPostCodeMandatory = 'true';
		}
		if($this->getConfigData('statemandatory'))
		{
			$boStateMandatory = 'true';
		}
		if($this->getConfigData('countrymandatory'))
		{
			$boCountryMandatory = 'true';
		}
		if($this->getConfigData('paymentformdisplaysresult'))
		{
			$boPaymentFormDisplaysResult = 'true';
		}

		$szHashDigest = CSV_PaymentFormHelper::calculateHashDigest($szMerchantID, $szPassword, $hmHashMethod, $szPreSharedKey, $nAmount, $nCurrencyCode, $szOrderID, $szTransactionType, $szTransactionDateTime, $szCallbackURL, $szOrderDescription, $szCustomerName, $szAddress1, $szAddress2, $szAddress3, $szAddress4, $szCity, $szState, $szPostCode, $nCountryCode, $boCV2Mandatory, $boAddress1Mandatory, $boCityMandatory, $boPostCodeMandatory, $boStateMandatory, $boCountryMandatory, $rdmResultdeliveryMethod, $szServerResultURL, $boPaymentFormDisplaysResult, $szServerResultURLCookieVariables, $szServerResultURLFormVariables, $szServerResultURLQueryStringVariables);

		$session->setHashdigest($szHashDigest)
	        	->setMerchantid($szMerchantID)
			  	->setAmount($nAmount)
			  	->setCurrencycode($nCurrencyCode)
			  	->setOrderid($szOrderID)
			  	->setTransactiontype($szTransactionType)
			  	->setTransactiondatetime($szTransactionDateTime)
			  	->setCallbackurl($szCallbackURL)
			  	->setOrderdescription($szOrderDescription)
			  	->setCustomername($szCustomerName)
			  	->setAddress1($szAddress1)
			  	->setAddress2($szAddress2)
			  	->setAddress3($szAddress3)
			  	->setAddress4($szAddress4)
			  	->setCity($szCity)
			  	->setState($szState)
			  	->setPostcode($szPostCode)
			  	->setCountrycode($nCountryCode)
			  	->setCv2mandatory($boCV2Mandatory)
			  	->setAddress1mandatory($boAddress1Mandatory)
			  	->setCitymandatory($boCityMandatory)
			  	->setPostcodemandatory($boPostCodeMandatory)
			  	->setStatemandatory($boStateMandatory)
			  	->setCountrymandatory($boCountryMandatory)
			  	->setResultdeliverymethod($rdmResultdeliveryMethod)
			  	->setServerresulturl($szServerResultURL)
			  	->setPaymentformdisplaysresult($boPaymentFormDisplaysResult)
			  	->setServerresulturlcookievariables($szServerResultURLCookieVariables)
			  	->setServerresulturlformvariables($szServerResultURLFormVariables)
			  	->setServerresulturlquerystringvariables($szServerResultURLQueryStringVariables);
			  	
		$session->setRedirectionmethod('_runRedirectedPaymentComplete');
		$payment->getOrder()->setIsHostedPaymentPending(true);
		
		/* serve out a dummy CrossReference as the TransactionId - this need to be done to enable the "Refund" button 
		   in the Magento CreditMemo internal refund mechanism */
		$payment->setTransactionId($szOrderID."_".date('YmdHis'));
	}
	
	/**
	 * Processing the transaction using the transparent redirect integration
	 *
	 * @param Varien_Object $payment
	 * @param unknown_type $amount
	 */
	public function _runTransparentRedirectTransaction(Varien_Object $payment, $amount)
	{
		$takePaymentInStoreBaseCurrency = $this->getConfigData('takePaymentInStoreBaseCurrency');
		
		$GLOBALS['m_boPayInvoice'] = false;
		$payment->setIsTransactionPending(true);
		$nVersion = $this->getVersion();
		
		$szMerchantID = $this->getConfigData('merchantid');
		$szPassword = $this->getConfigData('password');
		$szPreSharedKey = $this->getConfigData('presharedkey');
		$hmHashMethod = $this->getConfigData('hashmethod');
		$szCallbackURL = Mage::getUrl('cardsaveonlinepayments/payment/callbacktransparentredirect', array('_secure' => true));
		$order = $payment->getOrder();
		$billingAddress = $order->getBillingAddress();
		$iclISOCurrencyList = CSV_ISOCurrencies::getISOCurrencyList();
		$iclISOCountryList = CSV_ISOCountries::getISOCountryList();
		$szStartDateMonth = '';
		$szStartDateYear = '';
		
		if (!$takePaymentInStoreBaseCurrency) {	
			// Take payment in order currency
			$szCurrencyShort = $order->getOrderCurrency()->getCurrencyCode();
			if ($szCurrencyShort != '' && $iclISOCurrencyList->getISOCurrency($szCurrencyShort, $icISOCurrency))
			{
				$nCurrencyCode = $icISOCurrency->getISOCode();
			}
			
			// Calculate amount
			$power = pow(10, $icISOCurrency->getExponent());
			$nAmount = round($order->getGrandTotal() * $power,0);			
		} else {
			// Take payment in site base currency
			//$szCurrencyShort = $order->getOrderCurrency()->getCurrencyCode();
			$szCurrencyShort = $order->getBaseCurrencyCode();
			if ($szCurrencyShort != '' && $iclISOCurrencyList->getISOCurrency($szCurrencyShort, $icISOCurrency))
			{
				$nCurrencyCode = $icISOCurrency->getISOCode();
			}
			
			// Calculate amount
			$nAmount = $this->_getRoundedAmount($amount, $icISOCurrency->getExponent());			
		}
		
		$szOrderID = $payment->getOrder()->increment_id;
		//date time with 2008-12-01 14:12:00 +01:00 format
		$szTransactionDateTime = date('Y-m-d H:i:s P');
		$szOrderDescription = '';
		
		//$szTransactionType = 'SALE';
		$paymentAction = $this->getConfigData('payment_action');
		if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE_CAPTURE)
		{
			$szTransactionType = "SALE";
		}
		else if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE)
		{
			$szTransactionType = "PREAUTH";
		}
		else 
		{
			Mage::throwException('Unknown payment action: '.$paymentAction);
		}
		
		$szAddress1 = $billingAddress->getStreet1();
		$szAddress2 = $billingAddress->getStreet2();
		$szAddress3 = $billingAddress->getStreet3();
		$szAddress4 = $billingAddress->getStreet4();
		$szCity = $billingAddress->getCity();
		$szState = $billingAddress->getRegion();
		$szPostCode = $billingAddress->getPostcode();
		$szISO2CountryCode = $billingAddress->getCountry();
		$szCountryShort = $this->_getISO3Code($szISO2CountryCode);
		if($iclISOCountryList->getISOCountry($szCountryShort, $icISOCountry))
		{
			$nCountryCode = $icISOCountry->getISOCode();
		}
		
		$szCardName = $payment->getCcOwner();
		$szCardNumber = $payment->getCcNumber();
		$szExpiryDateMonth = $payment->getCcExpMonth();
		$szExpiryDateYear = $payment->getCcExpYear();
		if($payment->getCcSsStartMonth() != '')
		{
			$szStartDateMonth = $payment->getCcSsStartMonth();
		}
		if($payment->getCcSsStartYear() != '')
		{
			$szStartDateYear = $payment->getCcSsStartYear();
		}
		$szIssueNumber = $payment->getCcSsIssue();
		$szCV2 = $payment->getCcCid();
		
		$szHashDigest = CSV_PaymentFormHelper::calculateTransparentRedirectHashDigest($szMerchantID, $szPassword, $hmHashMethod, $szPreSharedKey, $nAmount, $nCurrencyCode, $szOrderID, $szTransactionType, $szTransactionDateTime, $szCallbackURL, $szOrderDescription);
		
		Mage::getSingleton('checkout/session')->setHashdigest($szHashDigest)
	        									->setMerchantid($szMerchantID)
			  		   							->setAmount($nAmount)
			  		   							->setCurrencycode($nCurrencyCode)
			  		   							->setOrderid($szOrderID)
			  		   							->setTransactiontype($szTransactionType)
			  		   							->setTransactiondatetime($szTransactionDateTime)
			  		   							->setCallbackurl($szCallbackURL)
			  		   							->setOrderdescription($szOrderDescription)
			  		   							->setAddress1($szAddress1)
			  		   							->setAddress2($szAddress2)
			  		   							->setAddress3($szAddress3)
			  		   							->setAddress4($szAddress4)
			  		   							->setCity($szCity)
			  		   							->setState($szState)
			  		   							->setPostcode($szPostCode)
			  		   							->setCountrycode($nCountryCode)
			  		   							->setCardname($szCardName)
			  		   							->setCardnumber($szCardNumber)
			  		   							->setExpirydatemonth($szExpiryDateMonth)
			  		   							->setExpirydateyear($szExpiryDateYear)
			  		   							->setStartdatemonth($szStartDateMonth)
			  		   							->setStartdateyear($szStartDateYear)
			  		   							->setIssuenumber($szIssueNumber)
			  		   							->setCv2($szCV2);

		Mage::getSingleton('checkout/session')->setRedirectionmethod('_runRedirectedPaymentComplete');
		$payment->getOrder()->setIsHostedPaymentPending(true);
		
		
		/* serve out a dummy CrossReference as the TransactionId - this need to be done to enable the "Refund" button 
		   in the Magento CreditMemo internal refund mechanism */
		$payment->setTransactionId($szOrderID."_".date('YmdHis'));
	}
	
	/**
	 * Processing the 3D Secure transaction
	 *
	 * @param Varien_Object $payment
	 * @param int $amount
	 * @param string $szPaRes
	 * @param string $szMD
	 */
	public function _run3DSecureTransaction(Varien_Object $payment, $szPaRes, $szMD)
	{
		$error = false;
		$message = '';
		$order = $payment->getOrder();
		$szOrderID = $payment->getOrder()->increment_id;
		$session = Mage::getSingleton('checkout/session');
		$nVersion = $this->getVersion();
		
		$MerchantID = $this->getConfigData('merchantid');
		$Password = $this->getConfigData('password');
		$SecretKey = $this->getConfigData('secretkey');
		
		$PaymentProcessorFullDomain = $this->_getPaymentProcessorFullDomain();
		
		$rgeplRequestGatewayEntryPointList = new CSV_RequestGatewayEntryPointList();
		$rgeplRequestGatewayEntryPointList->add("https://gw1.".$PaymentProcessorFullDomain, 100, 2);
		$rgeplRequestGatewayEntryPointList->add("https://gw2.".$PaymentProcessorFullDomain, 200, 2);
		$rgeplRequestGatewayEntryPointList->add("https://gw3.".$PaymentProcessorFullDomain, 300, 2);
		
		$tdsaThreeDSecureAuthentication = new CSV_ThreeDSecureAuthentication($rgeplRequestGatewayEntryPointList);
		$tdsaThreeDSecureAuthentication->getMerchantAuthentication()->setMerchantID($MerchantID);
		$tdsaThreeDSecureAuthentication->getMerchantAuthentication()->setPassword($Password);

		$tdsaThreeDSecureAuthentication->getThreeDSecureInputData()->setCrossReference($szMD);
		$tdsaThreeDSecureAuthentication->getThreeDSecureInputData()->setPaRES($szPaRes);

		$boTransactionProcessed = $tdsaThreeDSecureAuthentication->processTransaction($tdsarThreeDSecureAuthenticationResult, $todTransactionOutputData);
		
		if ($boTransactionProcessed == false)
		{
			// could not communicate with the payment gateway
			$szLogMessage = Cardsave_Cardsaveonlinepayments_Model_Common_GlobalErrors::ERROR_431;
			$message = Cardsave_Cardsaveonlinepayments_Model_Common_GlobalErrors::ERROR_431;
			Mage::log($szLogMessage);
		}
		else
		{
			$message = "Payment Processor Response: ".$tdsarThreeDSecureAuthenticationResult->getMessage();
			$szLogMessage = "3D Secure transaction could not be completed for OrderID: ".$szOrderID.". Response object: ";
			$szCrossReference = $todTransactionOutputData->getCrossReference();
			
			switch ($tdsarThreeDSecureAuthenticationResult->getStatusCode())
			{
				case 0:
					// status code of 0 - means transaction successful
					$szLogMessage = "3D Secure transaction successfully completed for OrderID: ".$szOrderID.". Response object: ";
					
					// serve out the CrossReference as a TransactionId in the Magento system
					$this->setPaymentAdditionalInformation($payment, $szCrossReference);
					
					// need to store the new CrossReference and only store it against the payment object in the payment controller class
					$session->setNewCrossReference($szCrossReference);
					break;
				case 5:
					// status code of 5 - means transaction declined
					$error = true;
					break;
				case 20:
					// status code of 20 - means duplicate transaction
					$szPreviousTransactionMessage = $tdsarThreeDSecureAuthenticationResult->getPreviousTransactionResult()->getMessage();
					$szLogMessage = "Duplicate transaction for OrderID: ".$szOrderID.". A duplicate transaction means that a transaction with these details has already been processed by the payment provider. The details of the original transaction: ".$szPreviousTransactionMessage.". Response object: ";
					
					if ($tdsarThreeDSecureAuthenticationResult->getPreviousTransactionResult()->getStatusCode()->getValue() == 0)
					{
						$message = $message.". A duplicate transaction means that a transaction with these details has already been processed by the payment provider. The details of the original transaction are - ".$szPreviousTransactionMessage;
					}
					else
					{
						$error = true;
					}
					break;
				case 30:
					$error = true;
					// status code of 30 - means an error occurred 
					$szLogMessage = "3D Secure transaction could not be completed for OrderID: ".$szOrderID.". Error message: ".$tdsarThreeDSecureAuthenticationResult->getMessage();
					if ($tdsarThreeDSecureAuthenticationResult->getErrorMessages()->getCount() > 0)
					{
						$szLogMessage = $szLogMessage.".";
						$message =$message."."; 
	
						for ($LoopIndex = 0; $LoopIndex < $tdsarThreeDSecureAuthenticationResult->getErrorMessages()->getCount(); $LoopIndex++)
						{
							$szLogMessage = $szLogMessage.$tdsarThreeDSecureAuthenticationResult->getErrorMessages()->getAt($LoopIndex).";";
							$message = $message.$tdsarThreeDSecureAuthenticationResult->getErrorMessages()->getAt($LoopIndex).";";
						}
						$szLogMessage = $szLogMessage." ";
						$message = $message." ";
					}
					break;
				default:
					// unhandled status code 
					$error = true; 
					break;
			}
			
			// log 3DS payment result
			$szLogMessage = $szLogMessage.print_r($tdsarThreeDSecureAuthenticationResult, 1);
			Mage::log($szLogMessage);
		}
		
		$session->setPaymentprocessorresponse($message);
		if($error == true)
		{
			$message = Mage::helper('cardsaveonlinepayments')->__($message);
			Mage::throwException($message);
		}
		else
		{
			$payment->setStatus(self::STATUS_APPROVED);
		}
		
		return $this;
	}
	
	public function _runRedirectedPaymentComplete(Varien_Object $payment, $boIsHostedPaymentAction, $szStatusCode, $szMessage, $szPreviousStatusCode, $szPreviousMessage, $szOrderID, $szCrossReference)
	{
		$error = false;
		$message;
		$session = Mage::getSingleton('checkout/session');
		$nVersion = $this->getVersion();
		
		if($boIsHostedPaymentAction == true)
		{
			$szWording = "Hosted Payment Form ";
		}
		else
		{
			$szWording = "Transparent Redirect ";
		}
		
		$message = "Payment Processor Response: ".$szMessage;
		    	
		switch ($szStatusCode)
    	{
    		case "0":
    			Mage::log($szWording."transaction successfully completed. ".$message);

    			// need to store the new CrossReference and only store it against the payment object in the payment controller class
				$session->setNewCrossReference($szCrossReference);
    			break;
    		case "20":
    			Mage::log("Duplicate ".$szWording."transaction. ".$message);
    			$message = $message.". A duplicate transaction means that a transaction with these details has already been processed by the payment provider. The details of the original transaction - Previous Transaction Response: ".$szPreviousMessage;
    			if($szPreviousStatusCode != "0")
    			{
	    			$error = true;
    			}
    			break;
    		case "5":
    		case "30":
    		default:
    			Mage::log($szWording."transaction couldn't be completed. ".$message);
    			$error = true;
    			break;
    	}
    	
    	$session->setPaymentprocessorresponse($message);
    	
    	// store the CrossReference and other data
    	$this->setPaymentAdditionalInformation($payment, $szCrossReference);
		
		if($error == true)
		{
			$message = Mage::helper('cardsaveonlinepayments')->__($message);
			Mage::throwException($message);
		}
		else
		{
			$payment->setStatus(self::STATUS_APPROVED);
		}
		
		return $this;
	}
	
	/**
	 * Override the core Mage function to get the URL to be redirected from the Onepage
	 *
	 * @return string
	 */
	public function getOrderPlaceRedirectUrl()
    {
    	$result = false;
       	$session = Mage::getSingleton('checkout/session');
     	$nVersion = $this->getVersion();
     	$mode = $this->getConfigData('mode');
     	
       	if($session->getMd() &&
       		$session->getAcsurl() &&
       		$session->getPareq())
       	{
       		// Direct (API) for 3D Secure payments       		
			// need to re-add the ordered item quantity to stock as per not completed 3DS transaction
			if($mode != Cardsave_Cardsaveonlinepayments_Model_Source_PaymentMode::PAYMENT_MODE_TRANSPARENT_REDIRECT)
			{
				$order = Mage::getModel('sales/order')->load(Mage::getSingleton('checkout/session')->getLastOrderId());
				$this->addOrderedItemsToStock($order);
			}
	    	
	    	
       		$result = Mage::getUrl('cardsaveonlinepayments/payment/threedsecure', array('_secure' => true));
       	}
       	if($session->getHashdigest())
       	{

			// need to re-add the ordered item quantity to stock as per not completed 3DS transaction
			if(!Mage::getSingleton('checkout/session')->getPares())
			{
				$order = Mage::getModel('sales/order')->load(Mage::getSingleton('checkout/session')->getLastOrderId());
				$this->addOrderedItemsToStock($order);
			}	    	
	    	
       		$result = Mage::getUrl('cardsaveonlinepayments/payment/redirect', array('_secure' => true));
       	}
        
        return $result;
    }
	
    /**
     * Get the correct payment processor domain
     *
     * @return string
     */
    private function _getPaymentProcessorFullDomain()
    {
    	$szPaymentProcessorFullDomain;
    	
    	// get the stored config setting
    	$szPaymentProcessorDomain = $this->getConfigData('paymentprocessordomain');
		$szPaymentProcessorPort = $this->getConfigData('paymentprocessorport');
    	
    	if ($szPaymentProcessorPort == '443')
		{
			$szPaymentProcessorFullDomain = $szPaymentProcessorDomain."/";
		}
		else
		{
			$szPaymentProcessorFullDomain = $szPaymentProcessorDomain.":".$szPaymentProcessorPort."/";
		}
		
		return $szPaymentProcessorFullDomain;
    }
    
    /**
     * Get the country ISO3 code from the ISO2 code
     *
     * @param ISO2Code
     * @return string
     */
	private function _getISO3Code($szISO2Code)
	{
		$szISO3Code;
		$collection;
		$boFound = false;
		$nCount = 1;
		$item;
		
		$collection = Mage::getModel('directory/country_api')->items();
		
		while ($boFound == false &&
				$nCount < count($collection))
		{
			$item = $collection[$nCount];
			if($item['iso2_code'] == $szISO2Code)
			{
				$boFound = true;
				$szISO3Code = $item['iso3_code'];
			}
			$nCount++;
		}
		
		return $szISO3Code;
	}
	
	/**
	* Transform the string Magento version number into an integer ready for comparison
	*
	* @param unknown_type $magentoVersion
	* @return unknown
	*/
	public function getVersion()
	{
		$magentoVersion = Mage::getVersion();
	   	$pattern = '/[^\d]/';
		$magentoVersion = preg_replace($pattern, '', $magentoVersion);
		
		while(strlen($magentoVersion) < 4)
		{
			$magentoVersion .= '0';
		}
		$magentoVersion = (int)$magentoVersion;
		
		return $magentoVersion;
	}
	
	private function _getRoundedAmount($amount, $nExponent)
	{
		$nDecimalAmount;
		
		// round the amount before use
		$amount = round($amount, $nExponent);
		$power = pow(10, $nExponent);
		$nDecimalAmount = $amount * $power;
		
		return $nDecimalAmount;
	}
	
	/**
	 * Depreciated function - sets the additional_information column data in the sales_flat_order_payment table
	 *
	 * @param unknown_type $payment
	 * @param unknown_type $szCrossReference
	 * @param unknown_type $szTransactionType
	 * @param unknown_type $szTransactionDate
	 */
	public function setPaymentAdditionalInformation($payment, $szCrossReference)
    {
    	$arAdditionalInformationArray = array();
    	
    	$paymentAction = $this->getConfigData('payment_action');
		if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE_CAPTURE)
		{
			$szTransactionType = "SALE";
		}
		else if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE)
		{
			$szTransactionType = "PREAUTH";
		}
		else 
		{
			Mage::throwException('Unknown payment action: '.$paymentAction);
		}
		
		$szTransactionDate = date("Ymd");
    	
    	$arAdditionalInformationArray["CrossReference"] = $szCrossReference;
    	$arAdditionalInformationArray["TransactionType"] = $szTransactionType;
    	$arAdditionalInformationArray["TransactionDateTime"] = $szTransactionDate;
    	
    	$payment->setAdditionalInformation($arAdditionalInformationArray);
    }
    
    /**
     * Deduct the order items from the stock
     *
     * @param unknown_type $order
     */
    public function subtractOrderedItemsFromStock($order)
    {
    	$nVersion = Mage::getModel('cardsaveonlinepayments/direct')->getVersion();
    	$isCustomStockManagementEnabled = Mage::getModel('cardsaveonlinepayments/direct')->getConfigData('customstockmanagementenabled');
    	
    	if($isCustomStockManagementEnabled)
    	{
	    	$items = $order->getAllItems();
			foreach ($items as $itemId => $item)
			{
				// ordered quantity of the item from stock
				$quantity = $item->getQtyOrdered();
				$productId = $item->getProductId();
				
				$stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
				$stockManagement = $stock->getManageStock();
				
				if($stockManagement)
				{
					$stock->setQty($stock->getQty() - $quantity);
					$stock->save();
				}
			}
    	}
    }
	
    /**
     * Re-add the order items to the stock to balance the incorrect stock management before a payment is completed
     *
     * @param unknown_type $order
     */
    public function addOrderedItemsToStock($order)
    {
    	$nVersion = Mage::getModel('cardsaveonlinepayments/direct')->getVersion();
    	$isCustomStockManagementEnabled = Mage::getModel('cardsaveonlinepayments/direct')->getConfigData('customstockmanagementenabled');
		
    	if($isCustomStockManagementEnabled)
    	{
	    	$items = $order->getAllItems();
			foreach ($items as $itemId => $item)
			{
				// ordered quantity of the item from stock
				$quantity = $item->getQtyOrdered();
				$productId = $item->getProductId();
				
				$stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
				$stockManagement = $stock->getManageStock();
				
				if($stockManagement)
				{
					$stock->setQty($stock->getQty() + $quantity);
					$stock->save();
				}
			}
    	}
    }
    
    /**
     * Override the refund function to run a CrossReference transaction
     *
     * @param Varien_Object $payment
     * @param unknown_type $amount
     * @return unknown
     */
    public function refund(Varien_Object $payment, $amount)
    {
        $error = false;
   		$szTransactionType = "REFUND";
   		$orderStatus = 'csv_refunded';
   		$szMessage = 'Payment refunded';
   		$arAdditionalInformationArray;
   		
        if($amount > 0)
        {
            $error = $this->_runCrossReferenceTransaction($payment, $szTransactionType, $amount);
        }
        else
        {
            $error = 'Error in refunding the payment';
        }
        
        if($error === false)
        {
        	$order = $payment->getOrder();
        	$payment = $order->getPayment();
        	$arAdditionalInformationArray = $payment->getAdditionalInformation();
        	
        	$arAdditionalInformationArray["Refunded"] = 1;
        	$payment->setAdditionalInformation($arAdditionalInformationArray);
        	$payment->save();
        	
        	$order->setState('canceled', $orderStatus, $szMessage, false);
        	$order->save();
        }
        else
        {
        	Mage::throwException($error);
        }
		
        return $this;
    }
    
    /**
     * CardSave VOID functionality
     * Note: if a transaction (payment) is once voided (canceled) it isn't possible to redo this action
     *
     * @param Varien_Object $payment
     * @return unknown
     */
    public function csvVoid(Varien_Object $payment)
    {
        $error = false;
        $szTransactionType = "VOID";
        $orderStatus = "csv_voided";
        $arAdditionalInformationArray;
        
        // attempt a VOID and accordingly to the last saved transaction id (CrossReference) set the new message 
        $szLastTransId = $payment->getLastTransId();
        $transaction = $payment->getTransaction($szLastTransId);
        $szMagentoTxnType = $transaction->getTxnType();
        $szMessage = "Payment voided";
        
        if($szMagentoTxnType == "capture")
        {
        	$szMessage = "Payment voided";
        }
        else if($szMagentoTxnType == "authorization")
        {
        	$szMessage = "PreAuthorization voided";
        }
        else if($szMagentoTxnType == "refund")
        {
        	$szMessage = "Refund voided";
        }
        else 
        {
        	// general message
        	$szMessage = "Payment voided";
        }
        
        $error = $this->_runCrossReferenceTransaction($payment, $szTransactionType);

        if ($error === false)
        {
        	$order = $payment->getOrder();
        	$invoices = $order->getInvoiceCollection();
        	$payment = $order->getPayment();
        	$arAdditionalInformationArray = $payment->getAdditionalInformation();
        	
        	$arAdditionalInformationArray["Voided"] = 1;
        	$payment->setAdditionalInformation($arAdditionalInformationArray);
        	$payment->save();
        	
        	// cancel the invoices
        	foreach ($invoices as $invoice)
        	{
        		$invoice->cancel();
        		$invoice->save();
        	}
        	
        	// udpate the order
        	$order->setActionBy($payment->getLggdInAdminUname())
		        	->setActionDate(date('Y-m-d H:i:s'))
		            ->setVoided(1)
		            ->setState('canceled', $orderStatus, $szMessage, false);
			$order->save();
			
			$result = "0";
        }
        else
        {
       		$result = $error;
        }

        return $result;
    }
    
    /**
     * CardSave COLLECTION functionality (capture called in Magento)
     *
     * @param Varien_Object $payment
     * @param unknown_type $szOrderID
     * @param unknown_type $szCrossReference
     * @return unknown
     */
    public function csvCollection(Varien_Object $payment, $szOrderID, $szCrossReference)
    {
    	$szTransactionType = "COLLECTION";
    	$orderStatus = 'csv_collected';
    	$szMessage = 'Preauthorization successfully collected';
    	$state = Mage_Sales_Model_Order::STATE_PROCESSING;
    	$arAdditionalInformationArray;
    	
    	$error = $this->_captureAuthorizedPayment($payment);
    	
    	if($error === false)
    	{
    		$order = $payment->getOrder();
    		$invoices = $order->getInvoiceCollection();
    		$payment = $order->getPayment();
        	$arAdditionalInformationArray = $payment->getAdditionalInformation();
        	
        	$arAdditionalInformationArray["Collected"] = 1;
        	$payment->setAdditionalInformation($arAdditionalInformationArray);
        	$payment->save();
        	
    		// update the invoices to paid status
        	foreach ($invoices as $invoice)
        	{
        		$invoice->pay()->save();
        	}
        	
        	$order->setActionBy($payment->getLggdInAdminUname())
		        	->setActionDate(date('Y-m-d H:i:s'))
		            ->setState($state, $orderStatus, $szMessage, false);
			$order->save();
    		
    		$result = "0";
    	}
    	else
    	{
    		$result = $error;
    	}
    	
    	return $result;
    }
    
    /**
     * Private capture function for an authorized payment
     *
     * @param Varien_Object $payment
     * @return unknown
     */
    private function _captureAuthorizedPayment(Varien_Object $payment)
    {
    	$error = false;
    	$session = Mage::getSingleton('checkout/session');
    	
    	try
    	{
    		// set the COLLECTION variable to true
    		$session->setIsCollectionCrossReferenceTransaction(true);
    		
	    	$invoice = $payment->getOrder()->prepareInvoice();
	        $invoice->register();
	        
	        if ($this->_canCapture)
	        {
	            $invoice->capture();
	        }
	
	        $payment->getOrder()->addRelatedObject($invoice);
	    	$payment->setCreatedInvoice($invoice);
    	}
    	catch(Exception $exc)
    	{
    		$error = "Couldn't capture pre-authorized payment. Message: ". $exc->getMessage();
    		Mage::log($exc->getMessage());
    	}
    	
    	// remove the COLLECTION session variable once finished the COLLECTION attempt
    	$session->setIsCollectionCrossReferenceTransaction(null);
    	
    	return $error;
    }
    
    /**
     * Internal CrossReference function for all VOID, REFUND, COLLECTION transaction types
     *
     * @param Varien_Object $payment
     * @param unknown_type $szTransactionType
     * @param unknown_type $amount
     * @return unknown
     */
    private function _runCrossReferenceTransaction(Varien_Object $payment, $szTransactionType, $amount = false)
    {
		$takePaymentInStoreBaseCurrency = $this->getConfigData('takePaymentInStoreBaseCurrency');
		
    	$error = false;
    	$boTransactionProcessed = false;
    	$PaymentProcessorFullDomain;
    	$rgeplRequestGatewayEntryPointList;
    	$crtCrossReferenceTransaction;
    	$crtrCrossReferenceTransactionResult;
    	$todTransactionOutputData;
    	$szMerchantID = $this->getConfigData('merchantid');
		$szPassword = $this->getConfigData('password');
		//
		$iclISOCurrencyList = CSV_ISOCurrencies::getISOCurrencyList();
		$szAmount;
		$nAmount;
		$szCurrencyShort;
		$iclISOCurrencyList;
    	$power;
    	$nDecimalAmount;
    	$szNewCrossReference;
    	
    	$order = $payment->getOrder();
    	$szOrderID = $order->getRealOrderId();;
    	//$szCrossReference = $payment->getLastTransId();
    	$additionalInformation = $payment->getAdditionalInformation();

    	$szCrossReference = $additionalInformation["CrossReference"];
    	$szCrossReference = $payment->getLastTransId();
    	
    	// check the CrossRference and TransactionType parameters
		if(!$szCrossReference)
		{
			$error = 'Error occurred for '.$szTransactionType.': Missing Cross Reference';
		}
		if(!$szTransactionType)
		{
			$error = 'Error occurred for '.$szTransactionType.': Missing Transaction Type';
		}
		
		if($error === false)
		{
			$PaymentProcessorFullDomain = $this->_getPaymentProcessorFullDomain();
			
	    	$rgeplRequestGatewayEntryPointList = new CSV_RequestGatewayEntryPointList();
			$rgeplRequestGatewayEntryPointList->add("https://gw1.".$PaymentProcessorFullDomain, 100, 2);
			$rgeplRequestGatewayEntryPointList->add("https://gw2.".$PaymentProcessorFullDomain, 200, 2);
			$rgeplRequestGatewayEntryPointList->add("https://gw3.".$PaymentProcessorFullDomain, 300, 2);
	    	
	    	$crtCrossReferenceTransaction = new CSV_CrossReferenceTransaction($rgeplRequestGatewayEntryPointList);
	    	$crtCrossReferenceTransaction->getMerchantAuthentication()->setMerchantID($szMerchantID);
			$crtCrossReferenceTransaction->getMerchantAuthentication()->setPassword($szPassword);
			
			if (!$takePaymentInStoreBaseCurrency) {		
				$power = pow(10, $icISOCurrency->getExponent());
				$nAmount = round($order->getGrandTotal() * $power,0);
			} else {
				$nAmount = $this->_getRoundedAmount($amount, $icISOCurrency->getExponent());
			}

			$szCurrencyShort = $order->getOrderCurrency()->getCurrencyCode();
			if ($szCurrencyShort != '' &&
				$iclISOCurrencyList->getISOCurrency($szCurrencyShort, $icISOCurrency))
			{
				$nCurrencyCode = new CSV_NullableInt($icISOCurrency->getISOCode());
				$crtCrossReferenceTransaction->getTransactionDetails()->getCurrencyCode()->setValue($icISOCurrency->getISOCode());
			}
			
			// round the amount before use
			//$nDecimalAmount = $this->_getRoundedAmount($nAmount, $icISOCurrency->getExponent());
			
			$crtCrossReferenceTransaction->getTransactionDetails()->setOrderID($szOrderID);
			$crtCrossReferenceTransaction->getTransactionDetails()->getAmount()->setValue($nAmount);
			
			$crtCrossReferenceTransaction->getTransactionDetails()->getMessageDetails()->setCrossReference($szCrossReference);
			$crtCrossReferenceTransaction->getTransactionDetails()->getMessageDetails()->setTransactionType($szTransactionType);
	    	
			try
			{
				$boTransactionProcessed = $crtCrossReferenceTransaction->processTransaction($crtrCrossReferenceTransactionResult, $todTransactionOutputData);
			}
			catch (Exception $exc)
			{
				Mage::log("exception: ".$exc->getMessage());
			}
	    	
			if ($boTransactionProcessed == false)
			{
				// could not communicate with the payment gateway
				$error = "Couldn't complete ".$szTransactionType." transaction. Details: ".$crtCrossReferenceTransaction->getLastException();
				$szLogMessage = $error;
			}
			else
			{
				switch($crtrCrossReferenceTransactionResult->getStatusCode())
				{
					case 0:
						$error = false;
						$szNewCrossReference = $todTransactionOutputData->getCrossReference();
						$szLogMessage = $szTransactionType . " CrossReference transaction successfully completed. Response object: ";
						
						$payment->setTransactionId($szNewCrossReference)
								->setParentTransactionId($szCrossReference)
								->setIsTransactionClosed(1);
						$payment->save();
						break;
					default:
						$szLogMessage = $crtrCrossReferenceTransactionResult->getMessage();
						if ($crtrCrossReferenceTransactionResult->getErrorMessages()->getCount() > 0)
						{
							$szLogMessage = $szLogMessage.".";
		
							for ($LoopIndex = 0; $LoopIndex < $crtrCrossReferenceTransactionResult->getErrorMessages()->getCount(); $LoopIndex++)
							{
								$szLogMessage = $szLogMessage.$crtrCrossReferenceTransactionResult->getErrorMessages()->getAt($LoopIndex).";";
							}
							$szLogMessage = $szLogMessage." ";
						}
					
						$error = "Couldn't complete ".$szTransactionType." transaction for CrossReference: " . $szCrossReference . ". Payment Response: ".$szLogMessage;
						$szLogMessage = $szTransactionType . " CrossReference transaction failed. Response object: ";
						break;
				}
				
				$szLogMessage = $szLogMessage.print_r($crtrCrossReferenceTransactionResult, 1);
			}
			
			Mage::log($szLogMessage);
		}
		
		return $error;
    }
}