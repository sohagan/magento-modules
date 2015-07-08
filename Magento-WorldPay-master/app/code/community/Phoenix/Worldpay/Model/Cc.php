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
 * @category   Phoenix
 * @package    Phoenix_Worldpay
 * @copyright  Copyright (c) 2010 Phoenix Medien GmbH & Co. KG (http://www.phoenix-medien.de)
 */


class Phoenix_Worldpay_Model_Cc extends Mage_Payment_Model_Method_Abstract

{
    const SIGNATURE_TYPE_STATIC  = 1;
    const SIGNATURE_TYPE_DYNAMIC = 2;

	/**
	* unique internal payment method identifier
	*
	* @var string [a-z0-9_]
	**/
	protected $_code = 'worldpay_cc';

    protected $_isGateway               = false;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = false;

    protected $_paymentMethod			= 'cc';
    protected $_defaultLocale			= 'en';

    protected $_testUrl	= 'https://secure-test.worldpay.com/wcc/purchase';
    protected $_liveUrl	= 'https://secure.worldpay.com/wcc/purchase';

    protected $_testAdminUrl	= 'https://secure-test.worldpay.com/wcc/iadmin';
    protected $_liveAdminUrl	= 'https://secure.worldpay.com/wcc/iadmin';

    protected $_formBlockType = 'worldpay/form';
    protected $_infoBlockType = 'worldpay/info';

    protected $_order;

    /**
     * Get order model
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
		if (!$this->_order) {
			$this->_order = $this->getInfoInstance()->getOrder();
		}
		return $this->_order;
    }

    public function getOrderPlaceRedirectUrl()
    {
          return Mage::getUrl('worldpay/processing/redirect');
    }

    /**
     * Return payment method type string
     *
     * @return string
     */
    public function getPaymentMethodType()
    {
        return $this->_paymentMethod;
    }

    public function getUrl()
    {
    	if ($this->getConfigData('transaction_mode') == 'live')
    		return $this->_liveUrl;
    	return $this->_testUrl;
    }

    public function getAdminUrl()
    {
    	if ($this->getConfigData('transaction_mode') == 'live')
    		return $this->_liveAdminUrl;
    	return $this->_testAdminUrl;
    }


    /**
     * prepare params array to send it to gateway page via POST
     *
     * @return array
     */
    public function getFormFields()
    {
	    	// get transaction amount and currency
        if ($this->getConfigData('use_store_currency')) {
        	$price      = number_format($this->getOrder()->getGrandTotal(),2,'.','');
        	$currency   = $this->getOrder()->getOrderCurrencyCode();
    	} else {
        	$price      = number_format($this->getOrder()->getBaseGrandTotal(),2,'.','');
        	$currency   = $this->getOrder()->getBaseCurrencyCode();
    	}

		$billing	= $this->getOrder()->getBillingAddress();

 		$locale = explode('_', Mage::app()->getLocale()->getLocaleCode());
		if (is_array($locale) && !empty($locale))
			$locale = $locale[0];
		else
			$locale = $this->getDefaultLocale();

    	$params = 	array(
	    				'instId'		=>	$this->getConfigData('inst_id'),
    					'cartId'		=>	$this->getOrder()->getRealOrderId() . '-' . $this->getOrder()->getQuoteId(),
	    				'authMode'		=>	($this->getConfigData('request_type') == self::ACTION_AUTHORIZE) ? 'E' : 'A',
    					'testMode'		=>	($this->getConfigData('transaction_mode') == 'test') ? '100' : '0',
	    				'amount'		=>	$price,
    					'currency'		=>	$currency,
    					'hideCurrency'	=>	'true',
    					'desc'			=>	$this->getConfigData('desc'),
						'name'			=>	Mage::helper('core')->removeAccents($billing->getFirstname().' '.$billing->getLastname()),
						'address'		=>	Mage::helper('core')->removeAccents($billing->getStreet(-1)).'&#10;'.Mage::helper('core')->removeAccents($billing->getCity()),
						'postcode'		=>	$billing->getPostcode() ,
						'country'		=>	$billing->getCountry(),
						'tel'			=>	$billing->getTelephone(),
						'email'			=>	$this->getOrder()->getCustomerEmail(),
						'lang'			=>	$locale,
						'MC_orderid'	=>	$this->getOrder()->getRealOrderId(),
    					'MC_callback'	=>	Mage::getUrl('worldpay/processing/response')
    				);

    		// set additional flags
    	if ($this->getConfigData('fix_contact') == 1)
    		$params['fixContact'] = 1;
    	if ($this->getConfigData('hide_contact') == 1)
    		$params['hideContact'] = 1;
        if ($this->getConfigData('hide_language_select') == 1)
                $params['noLanguageMenu'] = null;

			// add md5 hash
        $securityKey = trim($this->getConfigData('security_key'));
		if (empty($securityKey)) {
            return $params;
        }

        switch ($this->getConfigData('signature_type')) {
            case self::SIGNATURE_TYPE_STATIC :
                $signatureParams = explode(':', $this->getConfigData('signature_params'));
                $signatureString = $securityKey;
                foreach ($signatureParams as $param) {
                    if (array_key_exists($param, $params)) {
                        $signatureString .= ':' . $params[$param];
                    }
                }
                $params['signature'] = md5($signatureString);
                break;
            case self::SIGNATURE_TYPE_DYNAMIC :
                //'amount:currency:instId:cartId:authMode:email';
                $signatureParamsString = $this->getConfigData('signature_params');
                $signatureParams = explode(':', $signatureParamsString);
                $params['signatureFields'] = $signatureParamsString;
                $signatureString = $securityKey . ';' . $signatureParamsString;
                foreach ($signatureParams as $param) {
                    if (array_key_exists($param, $params)) {
                        $signatureString .= ';' . $params[$param];
                    }
                }
                $params['signature'] = md5($signatureString);
                break;
        }
        return $params;
    }

    /**
     * Refund money
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return  Phoenix_Worldpay_Model_Cc
     */
    public function refund(Varien_Object $payment, $amount)
    {
        $transactionId = $payment->getLastTransId();
        $params = $this->_prepareAdminRequestParams();

        $params['cartId']   = 'Refund';
        $params['op']       = 'refund-partial';
        $params['transId']  = $transactionId;
        $params['amount']   = $amount;
        $params['currency'] = $payment->getOrder()->getBaseCurrencyCode();

        $responseBody = $this->processAdminRequest($params);
        $response = explode(',', $responseBody);
        if (count($response) <= 0 || $response[0] != 'A' || $response[1] != $transactionId) {
            $message = $this->_getHelper()->__('Error during refunding online. Server response: %s', $responseBody);
            $this->_debug($message);
            Mage::throwException($message);
        }
        return $this;
    }

    /**
     * Capture preatutharized amount
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     */
	public function capture(Varien_Object $payment, $amount)
	{
        if (!$this->canCapture()) {
            return $this;
        }

        if (Mage::app()->getRequest()->getParam('transId')) {
            // Capture is called from response action
            $payment->setStatus(self::STATUS_APPROVED);
            return $this;
        }
        $transactionId = $payment->getLastTransId();
        $params = $this->_prepareAdminRequestParams();
        $params['transId']  = $transactionId;
        $params['authMode'] = '0';
        $params['op']       = 'postAuth-full';

        $responseBody = $this->processAdminRequest($params);
        $response = explode(',', $responseBody);

        if (count($response) <= 0 || $response[0] != 'A' || $response[1] != $transactionId) {
            $message = $this->_getHelper()->__('Error during capture online. Server response: %s', $responseBody);
            $this->_debug($message);
            Mage::throwException($message);
        } else {
            $payment->getOrder()->addStatusToHistory($payment->getOrder()->getStatus(), $this->_getHelper()->__('Worldpay transaction has been captured.'));
        }
    }


    /**
     * Check refund availability
     *
     * @return bool
     */
    public function canRefund ()
    {
        return $this->getConfigData('enable_online_operations');
    }

    public function canRefundInvoicePartial()
    {
        return $this->getConfigData('enable_online_operations');
    }

    public function canRefundPartialPerInvoice()
    {
        return $this->canRefundInvoicePartial();
    }

    public function canCapturePartial()
    {
        if (Mage::app()->getFrontController()->getAction()->getFullActionName() != 'adminhtml_sales_order_creditmemo_new'){
            return false;
        }
        return $this->getConfigData('enable_online_operations');
    }

	protected function processAdminRequest($params, $requestTimeout = 60)
	{
		try {
			$client = new Varien_Http_Client();
			$client->setUri($this->getAdminUrl())
				->setConfig(array('timeout'=>$requestTimeout,))
				->setParameterPost($params)
				->setMethod(Zend_Http_Client::POST);

			$response = $client->request();
			$responseBody = $response->getBody();

			if (empty($responseBody))
				Mage::throwException($this->_getHelper()->__('Worldpay API failure. The request has not been processed.'));
			// create array out of response

		} catch (Exception $e) {
            $this->_debug('Worldpay API connection error: '.$e->getMessage());
			Mage::throwException($this->_getHelper()->__('Worldpay API connection error. The request has not been processed.'));
		}

		return $responseBody;
	}

    protected function _prepareAdminRequestParams()
    {
        $params = array (
            'authPW'   => $this->getConfigData('auth_password'),
            'instId'   => $this->getConfigData('admin_inst_id'),
        );
        if ($this->getConfigData('transaction_mode') == 'test') {
            $params['testMode'] = 100;
        }
        return $params;
    }

    /**
     * Log debug data to file
     *
     * Prior Magento 1.4.1 this method doesn't exist. So it is mainly to provide
     * BC.
     *
     * @param mixed $debugData
     */
    protected function _debug($debugData)
    {
        if (method_exists($this, 'getDebugFlag')) {
            return parent::_debug($debugData);
        }

        if ($this->getConfigData('debug')) {
            Mage::log($debugData, null, 'payment_' . $this->getCode() . '.log', true);
        }
    }
}