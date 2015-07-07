<?php

if (!defined('COMPILER_INCLUDE_PATH')) {
	require_once getcwd()."/spectrum-magento-modules/current/CardSave/app/code/local/Cardsave/Cardsaveonlinepayments/Model/Common/PaymentFormHelper.php";
} else {
	include_once ("Cardsave_Cardsaveonlinepayments_Model_Common_PaymentFormHelper.php");
}

/**
 * Standard Checkout Controller
 *
 */
class Cardsave_Cardsaveonlinepayments_PaymentController extends Mage_Core_Controller_Front_Action
{
	protected function _expireAjax()
    {
        if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems())
        {
            $this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
            exit;
        }
    }

    public function errorAction()
    {
		//$this->_redirect('checkout/cart');
		$this->_redirect('checkout/onepage/failure');
        #$this->loadLayout();
        #$this->renderLayout();
    }

    /**
     * When a customer cancel payment.
     */
    public function cancelAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getPaypalStandardQuoteId(true));

        $this->_redirect('checkout/cart');
     }

	/**
     * Action logic for Hosted Payment mode
     *
     */
    public function redirectAction()
    {
    	$this->getResponse()->setBody($this->getLayout()->createBlock('cardsaveonlinepayments/redirect')->toHtml());
    }
    
    /**
     * Action logic for 3D Secure redirection
     *
     */
    public function threedsecureAction()
    {
    	$this->getResponse()->setBody($this->getLayout()->createBlock('cardsaveonlinepayments/threedsecure')->toHtml());
    }
    
    /**
     * Action logic for handling the reception of the 3D Secure authentication result (PaRes)
     *
     * @return unknown
     */
    public function callback3dAction()
    {
    	$boError = false;
    	$szMessage = '';
    	$checkout = Mage::getSingleton('checkout/type_onepage');
    	$session = Mage::getSingleton('checkout/session');
    	$szPaymentProcessorResponse = '';
    	$nVersion = Mage::getModel('cardsaveonlinepayments/direct')->getVersion();
    	$order = Mage::getModel('sales/order');
		$order->load(Mage::getSingleton('checkout/session')->getLastOrderId());
    	$boCartIsEmpty = false;
    	
    	try
    	{
    		$szPaRes = $this->getRequest()->getPost('PaRes');
    		$szMD = $this->getRequest()->getPost('MD');
    		
    		// check if the cart is not empty, ie: after successful completion back button clicked in the browser
    		$cardsaveOrderId = Mage::getSingleton('checkout/session')->getCardsaveonlinepaymentsOrderId();
    		$szOrderStatus = $order->getStatus();
    			
    		if($szOrderStatus != 'csv_paid' && $szOrderStatus != 'csv_preauth')
    		{
    			// cart is not empty
	    		// complete the 3D Secure transaction with the 3D Authorization result
	    		$checkout->saveOrderAfter3dSecure($szPaRes, $szMD);
	    		
	    		$szPaymentProcessorResponse = $session->getPaymentprocessorresponse();
    		}
    		else 
    		{
    			// cart is empty
    			$boCartIsEmpty = true;
    			$szPaymentProcessorResponse = null;
    		}
    	}
    	catch (Exception $exc)
    	{
    		$boError = true;
    		Mage::logException($exc);
    		
    		if( isset($_SESSION['cardsaveonlinepayments_message']) )
    		{
    			$szMessage = $_SESSION['cardsaveonlinepayments_message'];
    			unset($_SESSION['cardsaveonlinepayments_message']);
    		}
    		else
    		{
				$szMessage = Cardsave_Cardsaveonlinepayments_Model_Common_GlobalErrors::ERROR_7655;
    		}
    	}
		
		if ($boError)
    	{
    		if($szPaymentProcessorResponse != null &&
    			$szPaymentProcessorResponse != '')
    		{
    			$szMessage .= '<br/>'.$szPaymentProcessorResponse;
    		}			
			
			if($order)
			{
				$orderState = 'pending_payment';
				$orderStatus = 'csv_failed_threed_secure';
				$order->setCustomerNote(Mage::helper('cardsaveonlinepayments')->__('3D Secure Authentication Failed'));
				$order->setState($orderState, $orderStatus, $szPaymentProcessorResponse, false);
				$order->save();
			}
    		
			Mage::getSingleton('core/session')->addError($szMessage);
    		
    		$this->_clearSessionVariables();
			// report out an fatal error
    		$this->_redirect('checkout/onepage/failure');
    	}
    	else
    	{
		  	// set the quote as inactive after back from paypal
		 	Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
	
		 	// if the cart is empty do not attempt to update the invoices
		 	if($boCartIsEmpty == false)
		 	{
			  	// send confirmation email to customer
			    if($order->getId())
			    {
			  		$order->sendNewOrderEmail();
			    }
	
				$this->_updateInvoices($order, $szPaymentProcessorResponse);			    
			    
			    if($szPaymentProcessorResponse != '')
				{
					Mage::getSingleton('core/session')->addSuccess($szPaymentProcessorResponse);
				}
		 	}
		    
		    $this->_redirect('checkout/onepage/success', array('_secure' => true));
    	}
    }
    
    /**
     * Action logic for handling the result from the Hosted Payment page
     *
     */
    public function callbackhostedpaymentAction()
    {
		$boError = false;
    	$formVariables = array();
    	$model = Mage::getModel('cardsaveonlinepayments/direct');
    	$szOrderID = $this->getRequest()->getPost('OrderID');
        $checkout = Mage::getSingleton('checkout/type_onepage');
    	$session = Mage::getSingleton('checkout/session');
        $szPaymentProcessorResponse = '';
        $order = Mage::getModel('sales/order');
		$order->load(Mage::getSingleton('checkout/session')->getLastOrderId());
		$nVersion = Mage::getModel('cardsaveonlinepayments/direct')->getVersion();
		$boCartIsEmpty = false;
		
    	try
    	{
    		$hmHashMethod = $model->getConfigData('hashmethod');
			$szPassword = $model->getConfigData('password');
			$szPreSharedKey = $model->getConfigData('presharedkey');
    		
    		$formVariables['HashDigest'] = $this->getRequest()->getPost('HashDigest');
    		$formVariables['MerchantID'] = $this->getRequest()->getPost('MerchantID');
    		$formVariables['StatusCode'] = $this->getRequest()->getPost('StatusCode');
    		$formVariables['Message'] = $this->getRequest()->getPost('Message');
    		$formVariables['PreviousStatusCode'] = $this->getRequest()->getPost('PreviousStatusCode');
    		$formVariables['PreviousMessage'] = $this->getRequest()->getPost('PreviousMessage');
    		$formVariables['CrossReference'] = $this->getRequest()->getPost('CrossReference');
    		$formVariables['Amount'] = $this->getRequest()->getPost('Amount');
    		$formVariables['CurrencyCode'] = $this->getRequest()->getPost('CurrencyCode');
    		$formVariables['OrderID'] = $this->getRequest()->getPost('OrderID');
    		$formVariables['TransactionType'] = $this->getRequest()->getPost('TransactionType');
    		$formVariables['TransactionDateTime'] = $this->getRequest()->getPost('TransactionDateTime');
    		$formVariables['OrderDescription'] = $this->getRequest()->getPost('OrderDescription');
    		$formVariables['CustomerName'] = $this->getRequest()->getPost('CustomerName');
    		$formVariables['Address1'] = $this->getRequest()->getPost('Address1');
    		$formVariables['Address2'] = $this->getRequest()->getPost('Address2');
    		$formVariables['Address3'] = $this->getRequest()->getPost('Address3');
    		$formVariables['Address4'] = $this->getRequest()->getPost('Address4');
    		$formVariables['City'] = $this->getRequest()->getPost('City');
    		$formVariables['State'] = $this->getRequest()->getPost('State');
    		$formVariables['PostCode'] = $this->getRequest()->getPost('PostCode');
    		$formVariables['CountryCode'] = $this->getRequest()->getPost('CountryCode');
    		
    		if(!CSV_PaymentFormHelper::compareHostedPaymentFormHashDigest($formVariables, $szPassword, $hmHashMethod, $szPreSharedKey))
    		{
    			$boError = true;
    			$szNotificationMessage = "The payment was rejected for a SECURITY reason: the incoming payment data was tampered with.";
    			Mage::log("The Hosted Payment Form transaction couldn't be completed for the following reason: [".$szNotificationMessage. "]. Form variables: ".print_r($formVariables, 1));
    		}
    		else
    		{
    			$cardsaveOrderId = Mage::getSingleton('checkout/session')->getCardsaveonlinepaymentsOrderId();
    			$szOrderStatus = $order->getStatus();
    			$szStatusCode = $this->getRequest()->getPost('StatusCode');
	    		$szMessage = $this->getRequest()->getPost('Message');
	    		$szPreviousStatusCode = $this->getRequest()->getPost('PreviousStatusCode');
	    		$szPreviousMessage = $this->getRequest()->getPost('PreviousMessage');
	    		$szOrderID = $this->getRequest()->getPost('OrderID');
    			
    			if($szOrderStatus != 'csv_paid' &&
    				$szOrderStatus != 'csv_preauth')
    			{
    				$checkout->saveOrderAfterRedirectedPaymentAction(true,
    															$this->getRequest()->getPost('StatusCode'),
		    													$this->getRequest()->getPost('Message'),
		    													$this->getRequest()->getPost('PreviousStatusCode'),
		    													$this->getRequest()->getPost('PreviousMessage'),
		    													$this->getRequest()->getPost('OrderID'),
		    													$this->getRequest()->getPost('CrossReference'));
    			}
    			else 
    			{
    				// cart is empty
	    			$boCartIsEmpty = true;
	    			$szPaymentProcessorResponse = null;
	    			
	    			// chek the StatusCode as the customer might have just clicked the BACK button and re-submitted the card details
	    			// which can cause a charge back to the merchant
	    			$this->_fixBackButtonBug($szOrderID, $szStatusCode, $szMessage, $szPreviousStatusCode, $szPreviousMessage);
    			}
    		}
    	}
    	catch (Exception $exc)
    	{
    		$boError = true;
    		$szNotificationMessage = Cardsave_Cardsaveonlinepayments_Model_Common_GlobalErrors::ERROR_183;
    		Mage::logException($exc);
    	}
    	
    	$szPaymentProcessorResponse = $session->getPaymentprocessorresponse();
    	if($boError)
    	{
    		if($szPaymentProcessorResponse != null &&
    			$szPaymentProcessorResponse != '')
    		{
    			$szNotificationMessage = $szNotificationMessage.'<br/>'.$szPaymentProcessorResponse;
    		}
			
			$model->setPaymentAdditionalInformation($order->getPayment(), $this->getRequest()->getPost('CrossReference'));
    		//$order->getPayment()->setAdditionalData("CrossReference=".$this->getRequest()->getPost('CrossReference'));
      		
			if($order)
			{
				$orderState = 'pending_payment';
				$orderStatus = 'csv_failed_hosted_payment';
				$order->setCustomerNote(Mage::helper('cardsaveonlinepayments')->__('Hosted Payment Failed'));
				$order->setState($orderState, $orderStatus, $szPaymentProcessorResponse, false);
				$order->save();
			}
			
    		Mage::getSingleton('core/session')->addError($szNotificationMessage);
    		
			$order->save();
			
			$this->_clearSessionVariables();
    		$this->_redirect('checkout/onepage/failure');
    	}
    	else
    	{
    		// set the quote as inactive after back from paypal
		    Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();

		    if($boCartIsEmpty == false)
		    {
			    // send confirmation email to customer
		        if($order->getId())
		        {
		            $order->sendNewOrderEmail();
		        }	
		        
				$this->_updateInvoices($order, $szPaymentProcessorResponse);
		        		        
				if($szPaymentProcessorResponse != '')
				{
					Mage::getSingleton('core/session')->addSuccess($szPaymentProcessorResponse);
				}
		    }
		    
	        $this->_redirect('checkout/onepage/success', array('_secure' => true));
    	}
    }
    
	/**
     * Action logic for handling the server to server communication in case of Result Delivery Method = SERVER
     *
     */
    public function serverresultAction()
    {
    	$boError = false;
    	$model = Mage::getModel('cardsaveonlinepayments/direct');
    	$checkout = Mage::getSingleton('checkout/type_onepage');
    	$szOrderID = $this->getRequest()->getPost('OrderID');
    	$szMessage = $this->getRequest()->getPost('Message');
    	$nVersion = Mage::getModel('cardsaveonlinepayments/direct')->getVersion();
    												
    	try
    	{
    		// finish off the transaction: if StatusCode = 0 create an order otherwise do nothing
    		$checkout->saveOrderAfterRedirectedPaymentAction(true,
    														$this->getRequest()->getPost('StatusCode'),
		    												$szMessage,
		    												$this->getRequest()->getPost('PreviousStatusCode'),
		    												$this->getRequest()->getPost('PreviousMessage'),
		    												$this->getRequest()->getPost('OrderID'),
		    												$this->getRequest()->getPost('CrossReference'));
    	}
    	catch (Exception $exc)
    	{
    		$boError = true;
    		$szErrorMessage = $exc->getMessage();
    		$szNotificationMessage = Cardsave_Cardsaveonlinepayments_Model_Common_GlobalErrors::ERROR_183;
    		Mage::logException($exc);
    	}
    	
    	if($boError == true)
    	{
    		$this->getResponse()->setBody('StatusCode=30&Message='.$szErrorMessage);
    	}
    	else
    	{
    		$order = Mage::getModel('sales/order');
    		$order->load(Mage::getSingleton('checkout/session')->getLastOrderId());
    		// set the quote as inactive after back from paypal
		    Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();

		    // send confirmation email to customer
	        if($order->getId())
	        {
	            $order->sendNewOrderEmail();
	        }
	        
	        // if the payment was successful clear the session so that if the customer navigates back to the Magento store
	        // the shopping cart will be emptied rather than 'uncomplete'
	        if($this->getRequest()->getPost('StatusCode') == '0')
	        {
	        	Mage::getSingleton('checkout/session')->clear();				
				$this->_updateInvoices($order, $szMessage);	        	
	        }
	        
	        $this->getResponse()->setBody('StatusCode=0');
    	}
    }
    
    /*
     * Action logic to handle the SERVER_PUSH web request to the PaymentFormResultHandler.ashx to get the transaction result details 
     */
    public function serverpullresultAction()
    {
    	$boError = false;
    	$nStartIndex = false;
    	
    	$szHashDigest = false;
    	$szMerchantID = false;
    	$szCrossReference = false;
    	$szOrderID = false;
    	
    	$nErrorNumber = false;
    	$szErrorMessage = false;
    	$model = Mage::getModel('cardsaveonlinepayments/direct');
    	$checkout = Mage::getSingleton('checkout/type_onepage');
    	$szServerPullURL = $model->getConfigData('serverpullresultactionurl');
    	$szMerchantID = $model->getConfigData('merchantid');
    	$szPassword = $model->getConfigData('password');
    	$hmHashMethod = $model->getConfigData('hashmethod');
    	$szPreSharedKey = $model->getConfigData('presharedkey');
    	$szURLVariableString = $this->getRequest()->getRequestUri();
    	$nStartIndex = strpos($szURLVariableString, "?");
		$order = Mage::getModel('sales/order');
		$order->load(Mage::getSingleton('checkout/session')->getLastOrderId());
    	$nVersion = Mage::getModel('cardsaveonlinepayments/direct')->getVersion();
    	
    	if(!is_int($nStartIndex))
    	{
    		$szErrorMessage = Cardsave_Cardsaveonlinepayments_Model_Common_GlobalErrors::ERROR_309;
    		Mage::log(Cardsave_Cardsaveonlinepayments_Model_Common_GlobalErrors::ERROR_309." Request URI: ".$szURLVariableString);
    	}
    	else
    	{
	    	$szURLVariableString = substr($szURLVariableString, $nStartIndex + 1);
	    	$arFormVariables = CSV_PaymentFormHelper::getVariableCollectionFromString($szURLVariableString);
	    	
	    	if(!CSV_PaymentFormHelper::compareServerHashDigest($arFormVariables, $szPassword, $hmHashMethod, $szPreSharedKey))
	   		{
	   			// report an error message
	   			$szErrorMessage = Cardsave_Cardsaveonlinepayments_Model_Common_GlobalErrors::ERROR_304;
	   		}
	   		else
	   		{
	   			$szOrderID = $arFormVariables["OrderID"];
	   			$szCrossReference = $arFormVariables["CrossReference"];
	   			$szPostFields = "MerchantID=".$szMerchantID."&Password=".$szPassword."&CrossReference=".$szCrossReference;
	   			
	   			$cCurl = curl_init();
	   			curl_setopt($cCurl, CURLOPT_URL, $szServerPullURL);
	   			curl_setopt($cCurl, CURLOPT_POST, true);
	   			curl_setopt($cCurl, CURLOPT_POSTFIELDS, $szPostFields);
	   			curl_setopt($cCurl, CURLOPT_RETURNTRANSFER, 1);
	   			curl_setopt($cCurl, CURLOPT_ENCODING, "UTF-8");
	   			curl_setopt($cCurl, CURLOPT_SSL_VERIFYPEER, false);
	   			curl_setopt($cCurl, CURLOPT_SSL_VERIFYHOST, false);
	   			
	   			$response = curl_exec($cCurl);
	   			$nErrorNumber = curl_errno($cCurl);
				$szErrorMessage = curl_error($cCurl);
	   			curl_close($cCurl);
	   			
	   			if(is_int($nErrorNumber) &&
	   				$nErrorNumber > 0)
	   			{
	   				Mage::log("Error happened while trying to retrieve the transaction result details for a SERVER_PULL method for CrossReference: ".$szCrossReference.". Error code: ".$nErrorNumber.", message: ".$szErrorMessage);
	   				// suppress the message and use customer friendly instead
	   				$szErrorMessage = Cardsave_Cardsaveonlinepayments_Model_Common_GlobalErrors::ERROR_329." Message: ".$szErrorMessage;
	   			}
	   			else
	   			{
	   				// synchronize of the Magento backend with the transcation result
	   				try
	   				{
		   				// get the response items
		   				$responseItems = CSV_PaymentFormHelper::getVariableCollectionFromString($response);
		   				
		   				$szStatusCode = $responseItems["StatusCode"];
		   				$szMessage = $responseItems["Message"];
		   				$transactionResult = $responseItems["TransactionResult"];
		   				
		   				if($szStatusCode !== '0')
		   				{
		   					$szErrorMessage = Cardsave_Cardsaveonlinepayments_Model_Common_GlobalErrors::ERROR_381;
		   					$szErrorMessage .= " Message: ".$szMessage;
		   				}
		   				else
		   				{
		   					// URL decode the transaction result variable and get the transaction result sub variables
		   					$transactionResult = urldecode($transactionResult);
		   					$transactionResult = CSV_PaymentFormHelper::getVariableCollectionFromString($transactionResult);
		   					// create the order item in the Magento backend
			   				$szStatusCode = isset($transactionResult["StatusCode"]) ? $transactionResult["StatusCode"] : false;
				   			$szMessage = isset($transactionResult["Message"]) ? $transactionResult["Message"] : false;
				   			$szPreviousStatusCode = $szStatusCode;
							$szPreviousMessage = $szMessage;
						
		   					$checkout->saveOrderAfterRedirectedPaymentAction(true,
				    														$szStatusCode,
						    												$szMessage,
						    												$szPreviousStatusCode,
						    												$szPreviousMessage,
						    												$szOrderID,
						    												$szCrossReference);
		   				}
	   				}
	   				catch(Exception $exc)
	   				{
						$boError = true;
			    		$szErrorMessage = $exc->getMessage();
			    		Mage::logException($exc);
	   				}
	   			}
	   		}
    	}
    	
    	if($szErrorMessage)
		{
			$model->setPaymentAdditionalInformation($order->getPayment(), $szCrossReference);
			//$order->getPayment()->setAdditionalData("CrossReference=".$szCrossReference);
			
			if($order)
				{
					$orderState = 'pending_payment';
					$orderStatus = 'csv_failed_hosted_payment';
					$order->setCustomerNote(Mage::helper('cardsaveonlinepayments')->__('Hosted Payment Failed'));
					$order->setState($orderState, $orderStatus, $szErrorMessage, false);
					$order->save();
				}
				
			Mage::getSingleton('core/session')->addError($szErrorMessage);
			
			$order->save();
			
			$this->_clearSessionVariables();
		   	$this->_redirect('checkout/onepage/failure');
		}
		else
		{		
		   	// set the quote as inactive after back from paypal
			Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
		
			// send confirmation email to customer
			if($order->getId())
			{
			 	$order->sendNewOrderEmail();
			}
						
			$this->_updateInvoices($order, $szMessage);
			
			Mage::getSingleton('core/session')->addSuccess('Payment Processor Response: '.$szMessage);
			
		   	$this->_redirect('checkout/onepage/success', array('_secure' => true));
		}
    }
    
    /**
     * Action logic for handling the result set from the Transparent Redirect page
     *
     */
    public function callbacktransparentredirectAction()
    {
    	$model = Mage::getModel('cardsaveonlinepayments/direct');
		$order = Mage::getModel('sales/order');
		$order->load(Mage::getSingleton('checkout/session')->getLastOrderId());
		$nVersion = Mage::getModel('cardsaveonlinepayments/direct')->getVersion();
    	
    	try
    	{
    		$hmHashMethod = $model->getConfigData('hashmethod');
			$szPassword = $model->getConfigData('password');
			$szPreSharedKey = $model->getConfigData('presharedkey');
			
    		$szPaREQ = $this->getRequest()->getPost('PaREQ');
    		$szPaRES = $this->getRequest()->getPost('PaRes');
    		$nStatusCode = $this->getRequest()->getPost('StatusCode');
    		
    		if(isset($szPaREQ))
    		{
    			// 3D Secure authentication required
    			self::_threeDSecureAuthenticationRequired($szPassword, $hmHashMethod, $szPreSharedKey);
    		}
    		else if(isset($szPaRES))
    		{
    			// 3D Secure post authentication
    			self::_postThreeDSecureAuthentication($szPassword, $hmHashMethod, $szPreSharedKey);
    		}
    		else
    		{
    			// payment complete
    			self::_paymentComplete($szPassword, $hmHashMethod, $szPreSharedKey);
    		}
    		
    	}
    	catch (Exception $exc)
    	{
    		$error = Cardsave_Cardsaveonlinepayments_Model_Common_GlobalErrors::ERROR_260;
    		Mage::logException($exc);
			
    		if($order)
				{
					$orderState = 'pending_payment';
					$orderStatus = 'csv_failed_hosted_payment';
					$order->setCustomerNote(Mage::helper('cardsaveonlinepayments')->__('Transparent Redirect Payment Failed'));
					$order->setState($orderState, $orderStatus, $exc->getMessage(), false);
					$order->save();
				}
    		
    		Mage::getSingleton('core/session')->addError($error);
    		
    		
			$this->_clearSessionVariables();
    		$this->_redirect('checkout/onepage/failure');
    	}
    }
    
    private function _threeDSecureAuthenticationRequired($szPassword, $hmHashMethod, $szPreSharedKey)
    {
    	$error = false;
    	$formVariables = array();
    	
    	$formVariables['HashDigest'] = $this->getRequest()->getPost('HashDigest');
    	$formVariables['MerchantID'] = $this->getRequest()->getPost('MerchantID');
    	$formVariables['StatusCode'] = $this->getRequest()->getPost('StatusCode');
    	$formVariables['Message'] = $this->getRequest()->getPost('Message');
    	$formVariables['CrossReference'] = $this->getRequest()->getPost('CrossReference');
    	$formVariables['OrderID'] = $this->getRequest()->getPost('OrderID');
    	$formVariables['TransactionDateTime'] = $this->getRequest()->getPost('TransactionDateTime');
    	$formVariables['ACSURL'] = $this->getRequest()->getPost('ACSURL');
    	$formVariables['PaREQ'] = $this->getRequest()->getPost('PaREQ');
    	
    	if(!CSV_PaymentFormHelper::compareThreeDSecureAuthenticationRequiredHashDigest($formVariables, $szPassword, $hmHashMethod, $szPreSharedKey))
    	{
    		$error = "The payment was rejected for a SECURITY reason: the incoming payment data was tampered with.";
    		Mage::log("The Transparent Redirect transaction couldn't be completed for the following reason: ".$error. " Form variables: ".print_r($formVariables, 1));
    	}
    	
    	if($error)
    	{
			$this->_clearSessionVariables();
    		//Mage::getSingleton('core/session')->addError($error);
    		//$this->_redirect('checkout/onepage/failure');
			Mage::throwException($error);
    	}
    	else
    	{
    		// redirect to a secure 3DS authentication page
    		Mage::getSingleton('checkout/session')->setMd($formVariables['CrossReference'])
	        										->setAcsurl($formVariables['ACSURL'])
			  		   								->setPareq($formVariables['PaREQ'])
			  		   								->setTermurl('cardsaveonlinepayments/payment/callbacktransparentredirect');
			
			// redirect to a 3D Secure page
			$this->_redirect('cardsaveonlinepayments/payment/threedsecure');
    	}
    }
    
    private function _postThreeDSecureAuthentication($szPassword, $hmHashMethod, $szPreSharedKey)
    {
    	$error = false;
    	$formVariables = array();
    	$model = Mage::getModel('cardsaveonlinepayments/direct');

    	$szPaRES =  $this->getRequest()->getPost('PaRes');
    	$szCrossReference =  $this->getRequest()->getPost('MD');
    	$szMerchantID = $model->getConfigData('merchantid');
    	$szTransactionDateTime = date('Y-m-d H:i:s P');
    	$szCallbackURL = Mage::getUrl('cardsaveonlinepayments/payment/callbacktransparentredirect', array('_secure' => true));
    	$szHashDigest = CSV_PaymentFormHelper::calculatePostThreeDSecureAuthenticationHashDigest($szMerchantID, $szPassword, $hmHashMethod, $szPreSharedKey, $szPaRES, $szCrossReference, $szTransactionDateTime, $szCallbackURL);
    	
    	Mage::getSingleton('checkout/session')->setHashdigest($szHashDigest)
    											->setMerchantid($szMerchantID)
    											->setCrossreference($szCrossReference)
    											->setTransactiondatetime($szTransactionDateTime)
    											->setCallbackurl($szCallbackURL)
    											->setPares($szPaRES);
    	
    	// redirect to the redirection bridge page
    	$this->_redirect('cardsaveonlinepayments/payment/redirect');
    }
    
    private function _paymentComplete($szPassword, $hmHashMethod, $szPreSharedKey)
    {
    	$boError = false;
    	$formVariables = array();
    	$model = Mage::getModel('cardsaveonlinepayments/direct');
    	$szOrderID = $this->getRequest()->getPost('OrderID');
    	$checkout = Mage::getSingleton('checkout/type_onepage');
		$session = Mage::getSingleton('checkout/session');
        $szPaymentProcessorResponse = '';
		$order = Mage::getModel('sales/order');
		$order->load(Mage::getSingleton('checkout/session')->getLastOrderId());
        $nVersion = Mage::getModel('cardsaveonlinepayments/direct')->getVersion();
        $boCartIsEmpty = false;
        
    	try
    	{
		    $formVariables['HashDigest'] = $this->getRequest()->getPost('HashDigest');
		    $formVariables['MerchantID'] = $this->getRequest()->getPost('MerchantID');
		    $formVariables['StatusCode'] = $this->getRequest()->getPost('StatusCode');
		    $formVariables['Message'] = $this->getRequest()->getPost('Message');
		    $formVariables['PreviousStatusCode'] = $this->getRequest()->getPost('PreviousStatusCode');
		    $formVariables['PreviousMessage'] = $this->getRequest()->getPost('PreviousMessage');
		    $formVariables['CrossReference'] = $this->getRequest()->getPost('CrossReference');
		    $formVariables['Amount'] = $this->getRequest()->getPost('Amount');
		    $formVariables['CurrencyCode'] = $this->getRequest()->getPost('CurrencyCode');
		    $formVariables['OrderID'] = $this->getRequest()->getPost('OrderID');
		    $formVariables['TransactionType'] = $this->getRequest()->getPost('TransactionType');
		    $formVariables['TransactionDateTime'] = $this->getRequest()->getPost('TransactionDateTime');
		    $formVariables['OrderDescription'] = $this->getRequest()->getPost('OrderDescription');
		    $formVariables['Address1'] = $this->getRequest()->getPost('Address1');
		    $formVariables['Address2'] = $this->getRequest()->getPost('Address2');
		    $formVariables['Address3'] = $this->getRequest()->getPost('Address3');
		    $formVariables['Address4'] = $this->getRequest()->getPost('Address4');
		    $formVariables['City'] = $this->getRequest()->getPost('City');
		    $formVariables['State'] = $this->getRequest()->getPost('State');
		    $formVariables['PostCode'] = $this->getRequest()->getPost('PostCode');
		    $formVariables['CountryCode'] = $this->getRequest()->getPost('CountryCode');
			
		    $formVariables['AddressNumericCheckResult'] = $this->getRequest()->getPost('AddressNumericCheckResult');
		    $formVariables['PostCodeCheckResult'] = $this->getRequest()->getPost('PostCodeCheckResult');
		    $formVariables['CV2CheckResult'] = $this->getRequest()->getPost('CV2CheckResult');
		    $formVariables['ThreeDSecureAuthenticationCheckResult'] = $this->getRequest()->getPost('ThreeDSecureAuthenticationCheckResult');
		    $formVariables['CardType'] = $this->getRequest()->getPost('CardType');
		    $formVariables['CardClass'] = $this->getRequest()->getPost('CardClass');
		    $formVariables['CardIssuer'] = $this->getRequest()->getPost('CardIssuer');
		    $formVariables['CardIssuerCountryCode'] = $this->getRequest()->getPost('CardIssuerCountryCode');
			
		    $formVariables['EmailAddress'] = $this->getRequest()->getPost('EmailAddress');
		    $formVariables['PhoneNumber'] = $this->getRequest()->getPost('PhoneNumber');
		    
		    if(!CSV_PaymentFormHelper::comparePaymentCompleteHashDigest($formVariables, $szPassword, $hmHashMethod, $szPreSharedKey))
	    	{
	    		$boError = true;
	    		$szNotificationMessage = "The payment was rejected for a SECURITY reason: the incoming payment data was tampered with.";
	    		Mage::log("The Transparent Redirect transaction couldn't be completed for the following reason: [".$szNotificationMessage."] Form variables: ".print_r($formVariables, 1));
	    	}
	    	else
	    	{
	    		$cardsaveOrderId = Mage::getSingleton('checkout/session')->getCardsaveonlinepaymentsOrderId();
	    		$szOrderStatus = $order->getStatus();
    			
    			if($szOrderStatus != 'csv_paid' &&
    				$szOrderStatus != 'csv_preauth')
	    		{
		  			$checkout->saveOrderAfterRedirectedPaymentAction(false,
		  															$this->getRequest()->getPost('StatusCode'),
				    												$this->getRequest()->getPost('Message'),
				    												$this->getRequest()->getPost('PreviousStatusCode'),
				    												$this->getRequest()->getPost('PreviousMessage'),
				    												$this->getRequest()->getPost('OrderID'),
				    												$this->getRequest()->getPost('CrossReference'));
	    		}
	    		else 
	    		{
	    			$boCartIsEmpty = true;
	    			$szPaymentProcessorResponse = null;
	    			
	    			// chek the StatusCode as the customer might have just clicked the BACK button and re-submitted the card details
	    			// which can cause a charge back to the merchant
	    			$szStatusCode = $this->getRequest()->getPost('StatusCode');
	    			$szMessage = $this->getRequest()->getPost('Message');
	    			$szPreviousStatusCode = $this->getRequest()->getPost('PreviousStatusCode');
	    			$szPreviousMessage = $this->getRequest()->getPost('PreviousMessage');
	    			$szOrderID = $this->getRequest()->getPost('OrderID');
	    			
	    			$this->_fixBackButtonBug($szOrderID, $szStatusCode, $szMessage, $szPreviousStatusCode, $szPreviousMessage);
	    		}
	    	}
    	}
    	catch(Exception $exc)
    	{
    		$boError = true;
    		$szNotificationMessage = Cardsave_Cardsaveonlinepayments_Model_Common_GlobalErrors::ERROR_183;
    		Mage::logException($exc);
    	}
    	
    	$szPaymentProcessorResponse = $session->getPaymentprocessorresponse();
    	if($boError == true)
    	{
    		if($szPaymentProcessorResponse != null &&
	    		$szPaymentProcessorResponse != '')
	    	{
	    		$szNotificationMessage = $szNotificationMessage.'<br/>'.$szPaymentProcessorResponse;
	    	}
				
	    	$model->setPaymentAdditionalInformation($order->getPayment(), $this->getRequest()->getPost('CrossReference'));
	    	//$order->getPayment()->setAdditionalData("CrossReference=".$this->getRequest()->getPost('CrossReference'));
	    	
			if($order)
			{
				$orderState = 'pending_payment';
				$orderStatus = 'csv_failed_hosted_payment';
				$order->setCustomerNote(Mage::helper('cardsaveonlinepayments')->__('Transparent Redirect Payment Failed'));
				$order->setState($orderState, $orderStatus, $szPaymentProcessorResponse, false);
			}
	    		
			$order->save();
			
    		Mage::getSingleton('core/session')->addError($szNotificationMessage);
	    		    		
			$this->_clearSessionVariables();
    		$this->_redirect('checkout/onepage/failure');
    	}
    	else
    	{
			// set the quote as inactive after back from paypal
		    Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
		    
		    if($boCartIsEmpty == false)
		    {
			    // send confirmation email to customer
		        if($order->getId())
		        {
		            $order->sendNewOrderEmail();
		        }
	
		        $this->_updateInvoices($order, $szPaymentProcessorResponse);
		        
						        if($szPaymentProcessorResponse != '')
				{
					Mage::getSingleton('core/session')->addSuccess($szPaymentProcessorResponse);
				}			    
		    }		    
	        $this->_redirect('checkout/onepage/success', array('_secure' => true));
    	}
    }
    
    private function _clearSessionVariables()
    {
		// clear all the custom session variables used in the payment module in case of a failed payment
		Mage::getSingleton('checkout/session')->setHashdigest(null)
    											->setMerchantid(null)
    											->setCrossreference(null)
    											->setTransactiondatetime(null)
    											->setCallbackurl(null)
    											->setPareq(null)
    											->setPares(null)
												->setMd(null)
												->setAcsurl(null)
												->setTermurl(null)
												->setThreedsecurerequired(null)
												->setIshostedpayment(null)
												->setStatuscode(null)
												->setMessage(null)
												->setPreviousstatuscode(null)
												->setPreviousmessage(null)
												->setOrderid(null)
												->setRedirectedpayment(null);
												// do not clear the order id as after the a failed payment the customer still might want to repeat the payment attempt
											    //->setCardsaveonlinepaymentsOrderId(null);
    }
    
    /**
     * Set the invoice status to "Paid" after a successful payment
     *
     * @param unknown_type $order
     */
    private function _updateInvoices($order, $message)
    {
    	$invoices = $order->getInvoiceCollection();
    	$state = Mage_Sales_Model_Order::STATE_PROCESSING;
    	$payment = $order->getPayment();
    	$transaction;
    	$session = Mage::getSingleton('checkout/session');
    	$szNewCrossReference;
    	
    	$transactionId = $payment->getLastTransId();
    	$transaction = $payment->getTransaction($transactionId);
    	$transactionType = $transaction->getTxnType();
    	
    	if($session->getNewCrossReference())
    	{
    		$szNewCrossReference = $session->getNewCrossReference();
    		$value = $transaction->setTxnId($szNewCrossReference);
    		$transaction->save();
    		$payment->setLastTransId($szNewCrossReference);
    		
    		$session->setNewCrossReference(null);
    	}
    	
        foreach ($invoices as $invoice)
        {
       		// set the invoice state to be "Paid"
            $invoice->pay()->save();
        }
        // add a comment to the order comments
        if($transactionType == 'authorization')
        {
        	$order->setState($state, 'csv_preauth', $message, true);
        }
        else if($transactionType == 'capture')
        {
        	$order->setState($state, 'csv_paid', $message, true);
        }
        else 
        {
        	Mage::throwException('invalid transaction type [' . $transactionType . '] for invoice updating');
        }
        $order->save();
    }
    
    private function _fixBackButtonBug($szOrderID, $szStatusCode, $szMessage, $szPreviousStatusCode, $szPreviousMessage)
    {
    	// check the payment type as hitting the BACK button in the browser for Transparent Redirect payment method only redirects back the client side result and 
    	// not letting the customer to change the card details or re-submitting the payment
    	$mode = Mage::getModel('cardsaveonlinepayments/direct')->getConfigData('mode');
    	$boIgnoreDuplicateMessage = false;
    	
    	if($mode == Cardsave_Cardsaveonlinepayments_Model_Source_PaymentMode::PAYMENT_MODE_TRANSPARENT_REDIRECT)
    	{
    		$boIgnoreDuplicateMessage = true;	
    	}
    	
    	if($boIgnoreDuplicateMessage)
    	{
    		Mage::getSingleton('core/session')->addError('ERROR - Order ID: '.$szOrderID.' has already been successfully paid and processed. Payment Processor Response: '.$szMessage.'. <br/> IMPORTANT: please do not attempt to click the back button in your browser as it could cause duplicate charges to your bank account.');
    	}
    	else
    	{
	    	if($szStatusCode == '0')
		    {
		    	Mage::getSingleton('core/session')->addError('ERROR - Duplicate payment for Order ID: '.$szOrderID.' with Payment Processor Response: '.$szMessage.'. This order has already been successfully paid and processed. Please contact us immediately to avoid duplicate charges to your bank account.');
		    }
		    else if($szStatusCode == '20')
		    {
		    	Mage::getSingleton('core/session')->addError('Duplicate payment attempted for Order ID: '.$szOrderID.'. Previous Payment Processor Response: '.$szPreviousMessage.'. This order has already been successfully paid and processed. </br/>IMPORTANT: please do not attempt to click the back button in your browser and re-submit the payment for this order as it could cause duplicate charges to your bank account.');
		    }
		    else 
		    {
		    	Mage::getSingleton('core/session')->addError('ERROR: Order ID: '.$szOrderID.' has already been successfully paid and processed. Payment Processor Response: '.$szMessage.'. Please contact us immediately to avoid duplicate charges to your bank account.');
			}	
    	}
    }
    
    /**
     * Refund actioned when the user clicks the VOID button in the admin backend
     *
     * @return unknown
     */
    public function voidAction()
    {
    	$model = Mage::getSingleton('cardsaveonlinepayments/direct');
    	$parameters = $this->getRequest()->getParams();
    	$szOrderID = $parameters['OrderID'];
    	$szCrossReference = $parameters['CrossReference'];
    	
    	$order = Mage::getModel('sales/order')->loadByIncrementId((int)$szOrderID);
    	$payment = $order->getPayment();
    	
    	$result = Mage::getModel('cardsaveonlinepayments/direct')->csvVoid($payment);
    	
    	if($result == "0")
    	{
    		$model->addOrderedItemsToStock($order);
    	}
    	
    	return $this->getResponse()->setBody($result);
    }
    
    /**
     * Refund actioned when the user clicks the COLLECT button in the admin backend
     *
     * @return unknown
     */
    public function collectionAction()
    {
    	$parameters = $this->getRequest()->getParams();
    	$szOrderID = $parameters['OrderID'];
    	$szCrossReference = $parameters['CrossReference'];
    	
	  	$order = Mage::getModel('sales/order')->loadByIncrementId((int)$szOrderID);
    	$payment = $order->getPayment();
    	
    	$result = Mage::getModel('cardsaveonlinepayments/direct')->csvCollection($payment, $szOrderID, $szCrossReference);
    	
    	return $this->getResponse()->setBody($result);
    }
}