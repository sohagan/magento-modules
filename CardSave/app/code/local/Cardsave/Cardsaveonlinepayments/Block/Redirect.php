<?php 

class Cardsave_Cardsaveonlinepayments_Block_Redirect extends Mage_Core_Block_Abstract
{
	/**
	 * Build the redirect form to be submitted to the hosted payment form or the transparent redirect page
	 * 
	 */
	protected function _toHtml()
    {
    	$model = Mage::getModel('cardsaveonlinepayments/direct');
    	$pmPaymentMode = $model->getConfigData('mode');
    	
    	switch ($pmPaymentMode)
    	{
    		case Cardsave_Cardsaveonlinepayments_Model_Source_PaymentMode::PAYMENT_MODE_HOSTED_PAYMENT_FORM:
    			$html = self::_redirectToHostedPaymentForm();
    			break;
    		case Cardsave_Cardsaveonlinepayments_Model_Source_PaymentMode::PAYMENT_MODE_TRANSPARENT_REDIRECT:
    			$html = self::_redirectToTransparentRedirect();
    			break;
    	}

        return $html;
    }
    
    /**
     * Build the redirect form for the Hosted Payment Form payment type
     *
     * @return unknown
     */
    private function _redirectToHostedPaymentForm()
    {
    	$html = '';
    	$model = Mage::getModel('cardsaveonlinepayments/direct');
    	$szActionURL = $model->getConfigData('hostedpaymentactionurl');
    	$cookies = Mage::getSingleton('core/cookie')->get();
    	$szServerResultURLCookieVariables;
    	$szServerResultURLFormVariables = '';
    	$szServerResultURLQueryStringVariables = '';
    	
    	// create a Magento form
        $form = new Varien_Data_Form();
        $form->setAction($szActionURL)
            ->setId('HostedPaymentForm')
            ->setName('HostedPaymentForm')
            ->setMethod('POST')
            ->setUseContainer(true);

        $form->addField("HashDigest", 'hidden', array('name'=>"HashDigest", 'value'=>Mage::getSingleton('checkout/session')->getHashdigest()));
        $form->addField("MerchantID", 'hidden', array('name'=>"MerchantID", 'value'=>Mage::getSingleton('checkout/session')->getMerchantid()));
        $form->addField("Amount", 'hidden', array('name'=>"Amount", 'value'=>Mage::getSingleton('checkout/session')->getAmount()));
        $form->addField("CurrencyCode", 'hidden', array('name'=>"CurrencyCode", 'value'=>Mage::getSingleton('checkout/session')->getCurrencycode()));
        $form->addField("OrderID", 'hidden', array('name'=>"OrderID", 'value'=>Mage::getSingleton('checkout/session')->getOrderid()));
        $form->addField("TransactionType", 'hidden', array('name'=>"TransactionType", 'value'=>Mage::getSingleton('checkout/session')->getTransactiontype()));
        $form->addField("TransactionDateTime", 'hidden', array('name'=>"TransactionDateTime", 'value'=>Mage::getSingleton('checkout/session')->getTransactiondatetime()));
        $form->addField("CallbackURL", 'hidden', array('name'=>"CallbackURL", 'value'=>Mage::getSingleton('checkout/session')->getCallbackurl()));
        $form->addField("OrderDescription", 'hidden', array('name'=>"OrderDescription", 'value'=>Mage::getSingleton('checkout/session')->getOrderdescription()));
        $form->addField("CustomerName", 'hidden', array('name'=>"CustomerName", 'value'=>Mage::getSingleton('checkout/session')->getCustomername()));
        $form->addField("Address1", 'hidden', array('name'=>"Address1", 'value'=>Mage::getSingleton('checkout/session')->getAddress1()));
        $form->addField("Address2", 'hidden', array('name'=>"Address2", 'value'=>Mage::getSingleton('checkout/session')->getAddress2()));
        $form->addField("Address3", 'hidden', array('name'=>"Address3", 'value'=>Mage::getSingleton('checkout/session')->getAddress3()));
        $form->addField("Address4", 'hidden', array('name'=>"Address4", 'value'=>Mage::getSingleton('checkout/session')->getAddress4()));
        $form->addField("City", 'hidden', array('name'=>"City", 'value'=>Mage::getSingleton('checkout/session')->getCity()));
        $form->addField("State", 'hidden', array('name'=>"State", 'value'=>Mage::getSingleton('checkout/session')->getState()));
        $form->addField("PostCode", 'hidden', array('name'=>"PostCode", 'value'=>Mage::getSingleton('checkout/session')->getPostcode()));
        $form->addField("CountryCode", 'hidden', array('name'=>"CountryCode", 'value'=>Mage::getSingleton('checkout/session')->getCountrycode()));
        $form->addField("CV2Mandatory", 'hidden', array('name'=>"CV2Mandatory", 'value'=>Mage::getSingleton('checkout/session')->getCv2mandatory()));
        $form->addField("Address1Mandatory", 'hidden', array('name'=>"Address1Mandatory", 'value'=>Mage::getSingleton('checkout/session')->getAddress1mandatory()));
        $form->addField("CityMandatory", 'hidden', array('name'=>"CityMandatory", 'value'=>Mage::getSingleton('checkout/session')->getCitymandatory()));
        $form->addField("PostCodeMandatory", 'hidden', array('name'=>"PostCodeMandatory", 'value'=>Mage::getSingleton('checkout/session')->getPostcodemandatory()));
        $form->addField("StateMandatory", 'hidden', array('name'=>"StateMandatory", 'value'=>Mage::getSingleton('checkout/session')->getStatemandatory()));
        $form->addField("CountryMandatory", 'hidden', array('name'=>"CountryMandatory", 'value'=>Mage::getSingleton('checkout/session')->getCountrymandatory()));
        $form->addField("ResultDeliveryMethod", 'hidden', array('name'=>"ResultDeliveryMethod", 'value'=>Mage::getSingleton('checkout/session')->getResultdeliverymethod()));
        $form->addField("ServerResultURL", 'hidden', array('name'=>"ServerResultURL", 'value'=>Mage::getSingleton('checkout/session')->getServerresulturl()));
        $form->addField("PaymentFormDisplaysResult", 'hidden', array('name'=>"PaymentFormDisplaysResult", 'value'=>Mage::getSingleton('checkout/session')->getPaymentformdisplaysresult()));
		$form->addField("ServerResultURLCookieVariables", 'hidden', array('name'=>"ServerResultURLCookieVariables", 'value'=>Mage::getSingleton('checkout/session')->getServerresulturlcookievariables()));
        $form->addField("ServerResultURLFormVariables", 'hidden', array('name'=>"ServerResultURLFormVariables", 'value'=>Mage::getSingleton('checkout/session')->getServerresulturlformvariables()));
        $form->addField("ServerResultURLQueryStringVariables", 'hidden', array('name'=>"ServerResultURLQueryStringVariables", 'value'=>Mage::getSingleton('checkout/session')->getServerresulturlquerystringvariables()));
        
        // reset the session items
        Mage::getSingleton('checkout/session')->setHashdigest(null)
	        									->setMerchantid(null)
			  		   							->setAmount(null)
			  		   							->setCurrencycode(null)
			  		   							->setOrderid(null)
			  		   							->setTransactiontype(null)
			  		   							->setTransactiondatetime(null)
			  		   							->setCallbackurl(null)
			  		   							->setOrderdescription(null)
			  		   							->setCustomername(null)
			  		   							->setAddress1(null)
			  		   							->setAddress2(null)
			  		   							->setAddress3(null)
			  		   							->setAddress4(null)
			  		   							->setCity(null)
			  		   							->setState(null)
			  		   							->setPostcode(null)
			  		   							->setCountrycode(null)
			  		   							->setCv2mandatory(null)
			  		   							->setAddress1mandatory(null)
			  		   							->setCitymandatory(null)
			  		   							->setPostcodemandatory(null)
			  		   							->setStatemandatory(null)
			  		   							->setCountrymandatory(null)
			  		   							->setResultdeliverymethod(null)
			  		   							->setServerresulturl(null)
			  		   							->setPaymentformdisplaysresult(null)
			  		   							->setServerresulturlcookievariables(null)
			  		   							->setServerresulturlformvariables(null)
			  		   							->setServerresulturlquerystringvariables(null);
        
        $html = '<html><body>';
        $html.= $this->__('You will be redirected to a secure payment page in a few seconds.');
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("HostedPaymentForm").submit();</script>';
        $html.= '</body></html>';
    	
    	return $html;
    }
    
    /**
     * Build the redirect form for the Transparent Redirect payment type
     *
     * @return unknown
     */
    private function _redirectToTransparentRedirect()
    {
    	$html;
    	$model = Mage::getModel('cardsaveonlinepayments/direct');
    	$szActionURL = $model->getConfigData('transparentredirectactionurl');
    	$szPaRes = Mage::getSingleton('checkout/session')->getPares();
    	
    	if(isset($szPaRes))
    	{
    		$html = self::_submitPaRes($szActionURL);
    	}
    	else
    	{
    		$html = self::_submitTransaction($szActionURL);
    	}
    	
    	return $html;
    }
    
    /**
     * Build the submit form for Transparent redirect payment type
     *
     * @param unknown_type $szActionURL
     * @return unknown
     */
    private function _submitTransaction($szActionURL)
    {
    	$html = '';
    	
    	// create a Magento form
        $form = new Varien_Data_Form();
        $form->setAction($szActionURL)
            ->setId('TransparentRedirectForm')
            ->setName('TransparentRedirectForm')
            ->setMethod('POST')
            ->setUseContainer(true);
    	
      	$form->addField("HashDigest", 'hidden', array('name'=>"HashDigest", 'value'=>Mage::getSingleton('checkout/session')->getHashdigest()));
      	$form->addField("MerchantID", 'hidden', array('name'=>"MerchantID", 'value'=>Mage::getSingleton('checkout/session')->getMerchantid()));
      	$form->addField("Amount", 'hidden', array('name'=>"Amount", 'value'=>Mage::getSingleton('checkout/session')->getAmount()));
      	$form->addField("CurrencyCode", 'hidden', array('name'=>"CurrencyCode", 'value'=>Mage::getSingleton('checkout/session')->getCurrencycode()));
      	$form->addField("OrderID", 'hidden', array('name'=>"OrderID", 'value'=>Mage::getSingleton('checkout/session')->getOrderid()));
      	$form->addField("TransactionType", 'hidden', array('name'=>"TransactionType", 'value'=>Mage::getSingleton('checkout/session')->getTransactiontype()));
      	$form->addField("TransactionDateTime", 'hidden', array('name'=>"TransactionDateTime", 'value'=>Mage::getSingleton('checkout/session')->getTransactiondatetime()));
      	$form->addField("CallbackURL", 'hidden', array('name'=>"CallbackURL", 'value'=>Mage::getSingleton('checkout/session')->getCallbackurl()));
      	$form->addField("OrderDescription", 'hidden', array('name'=>"OrderDescription", 'value'=>Mage::getSingleton('checkout/session')->getOrderdescription()));
      	$form->addField("Address1", 'hidden', array('name'=>"Address1", 'value'=>Mage::getSingleton('checkout/session')->getAddress1()));
      	$form->addField("Address2", 'hidden', array('name'=>"Address2", 'value'=>Mage::getSingleton('checkout/session')->getAddress2()));
      	$form->addField("Address3", 'hidden', array('name'=>"Address3", 'value'=>Mage::getSingleton('checkout/session')->getAddress3()));
      	$form->addField("Address4", 'hidden', array('name'=>"Address4", 'value'=>Mage::getSingleton('checkout/session')->getAddress4()));
      	$form->addField("City", 'hidden', array('name'=>"City", 'value'=>Mage::getSingleton('checkout/session')->getCity()));
      	$form->addField("State", 'hidden', array('name'=>"State", 'value'=>Mage::getSingleton('checkout/session')->getState()));
      	$form->addField("PostCode", 'hidden', array('name'=>"PostCode", 'value'=>Mage::getSingleton('checkout/session')->getPostcode()));
      	$form->addField("CountryCode", 'hidden', array('name'=>"CountryCode", 'value'=>Mage::getSingleton('checkout/session')->getCountrycode()));
      	$form->addField("CardName", 'hidden', array('name'=>"CardName", 'value'=>Mage::getSingleton('checkout/session')->getCardname()));
      	$form->addField("CardNumber", 'hidden', array('name'=>"CardNumber", 'value'=>Mage::getSingleton('checkout/session')->getCardnumber()));
      	$form->addField("ExpiryDateMonth", 'hidden', array('name'=>"ExpiryDateMonth", 'value'=>Mage::getSingleton('checkout/session')->getExpirydatemonth()));
      	$form->addField("ExpiryDateYear", 'hidden', array('name'=>"ExpiryDateYear", 'value'=>Mage::getSingleton('checkout/session')->getExpirydateyear()));
      	$form->addField("StartDateMonth", 'hidden', array('name'=>"StartDateMonth", 'value'=>Mage::getSingleton('checkout/session')->getStartdatemonth()));
      	$form->addField("StartDateYear", 'hidden', array('name'=>"StartDateYear", 'value'=>Mage::getSingleton('checkout/session')->getStartdateyear()));
      	$form->addField("IssueNumber", 'hidden', array('name'=>"IssueNumber", 'value'=>Mage::getSingleton('checkout/session')->getIssuenumber()));
      	$form->addField("CV2", 'hidden', array('name'=>"CV2", 'value'=>Mage::getSingleton('checkout/session')->getCv2()));
		$form->addField("PostAuthCompatMode", 'hidden', array('name'=>"PostAuthCompatMode", 'value'=>"false"));
		$form->addField("ThreeDSecureCompatMode", 'hidden', array('name'=>"ThreeDSecureCompatMode", 'value'=>"false"));
		$form->addField("EchoAVSCheckResult", 'hidden', array('name'=>"EchoAVSCheckResult", 'value'=>"true"));
		$form->addField("EchoCV2CheckResult", 'hidden', array('name'=>"EchoCV2CheckResult", 'value'=>"true"));
		$form->addField("EchoThreeDSecureAuthenticationCheckResult", 'hidden', array('name'=>"EchoThreeDSecureAuthenticationCheckResult", 'value'=>"true"));
		$form->addField("EchoCardType", 'hidden', array('name'=>"EchoCardType", 'value'=>"true"));
      	
       	 // reset the session items
        Mage::getSingleton('checkout/session')->setHashdigest(null)
	        									->setMerchantid(null)
			  		   							->setAmount(null)
			  		   							->setCurrencycode(null)
			  		   							->setOrderid(null)
			  		   							->setTransactiontype(null)
			  		   							->setTransactiondatetime(null)
			  		   							->setCallbackurl(null)
			  		   							->setOrderdescription(null)
			  		   							->setAddress1(null)
			  		   							->setAddress2(null)
			  		   							->setAddress3(null)
			  		   							->setAddress4(null)
			  		   							->setCity(null)
			  		   							->setState(null)
			  		   							->setPostcode(null)
			  		   							->setCountrycode(null)
			  		   							->setCardname(null)
			  		   							->setCardnumber(null)
			  		   							->setExpirydatemonth(null)
			  		   							->setExpirydateyear(null)
			  		   							->setStartdatemonth(null)
			  		   							->setStartdateyear(null)
			  		   							->setIssuenumber(null)
			  		   							->setCv2(null);
        
        
        $html = '<html><body>';
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("TransparentRedirectForm").submit();</script>';
        $html.= '</body></html>';
    	
    	return $html;
    }
    
    /**
     * Build the form for the Transparent Redirect 3DSecure authentication payment 
     *
     * @param unknown_type $szActionURL
     * @return unknown
     */
    private function _submitPaRes($szActionURL)
    {
    	$html = '';
    	
    	// create a Magento form
        $form = new Varien_Data_Form();
        $form->setAction($szActionURL)
	            ->setId('SubmitPaResForm')
	            ->setName('SubmitPaResForm')
	            ->setMethod('POST')
	            ->setUseContainer(true);
    	
      	$form->addField("HashDigest", 'hidden', array('name'=>"HashDigest", 'value'=>Mage::getSingleton('checkout/session')->getHashdigest()));
      	$form->addField("MerchantID", 'hidden', array('name'=>"MerchantID", 'value'=>Mage::getSingleton('checkout/session')->getMerchantid()));
      	$form->addField("CrossReference", 'hidden', array('name'=>"CrossReference", 'value'=>Mage::getSingleton('checkout/session')->getCrossreference()));
      	$form->addField("TransactionDateTime", 'hidden', array('name'=>"TransactionDateTime", 'value'=>Mage::getSingleton('checkout/session')->getTransactiondatetime()));
      	$form->addField("CallbackURL", 'hidden', array('name'=>"CallbackURL", 'value'=>Mage::getSingleton('checkout/session')->getCallbackurl()));
      	$form->addField("PaRES", 'hidden', array('name'=>"PaRES", 'value'=>Mage::getSingleton('checkout/session')->getPares()));
		$form->addField("PostAuthCompatMode", 'hidden', array('name'=>"PostAuthCompatMode", 'value'=>"false"));
		$form->addField("ThreeDSecureCompatMode", 'hidden', array('name'=>"ThreeDSecureCompatMode", 'value'=>"false"));
      	
       	 // reset the session items
        Mage::getSingleton('checkout/session')->setHashdigest(null)
    											->setMerchantid(null)
    											->setCrossreference(null)
    											->setTransactiondatetime(null)
    											->setCallbackurl(null)
    											->setPares(null);
        
        
        $html = '<html><body>';
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("SubmitPaResForm").submit();</script>';
        $html.= '</body></html>';
    	
    	return $html;
    }
}