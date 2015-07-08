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

class Phoenix_Worldpay_ProcessingController extends Mage_Core_Controller_Front_Action
{
    protected $_successBlockType = 'worldpay/success';
    protected $_failureBlockType = 'worldpay/failure';
    protected $_cancelBlockType = 'worldpay/cancel';

    protected $_order = NULL;
    protected $_paymentInst = NULL;


    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * when customer selects Worldpay payment method
     */
    public function redirectAction()
    {
        try {
            $session = $this->_getCheckout();

            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($session->getLastRealOrderId());
            if (!$order->getId()) {
                Mage::throwException('No order for processing found');
            }
            if ($order->getState() != Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                $order->setState(
                    Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                    $this->_getPendingPaymentStatus(),
                    Mage::helper('worldpay')->__('Customer was redirected to Worldpay.')
                )->save();
            }

            if ($session->getQuoteId() && $session->getLastSuccessQuoteId()) {
                $session->setWorldpayQuoteId($session->getQuoteId());
                $session->setWorldpaySuccessQuoteId($session->getLastSuccessQuoteId());
                $session->setWorldpayRealOrderId($session->getLastRealOrderId());
                $session->getQuote()->setIsActive(false)->save();
                $session->clear();
            }

            $this->loadLayout();
            $this->renderLayout();
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch(Exception $e) {
            $this->_debug('Worldpay error: ' . $e->getMessage());
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * Worldpay returns POST variables to this action
     */
    public function responseAction()
    {
        try {
            $request = $this->_checkReturnedPost();
            if ($request['transStatus'] == 'Y') {
                $this->_processSale($request);
            } elseif ($request['transStatus'] == 'C') {
                $this->_processCancel($request);
            } else {
                Mage::throwException('Transaction was not successful.');
            }
        } catch (Mage_Core_Exception $e) {
            $this->_debug('Worldpay response error: ' . $e->getMessage());
            $this->getResponse()->setBody(
                $this->getLayout()
                    ->createBlock($this->_failureBlockType)
                    ->setOrder($this->_order)
                    ->toHtml()
            );
        }
    }

    /**
     * Worldpay return action
     */
    public function successAction()
    {
        try {
            $session = $this->_getCheckout();
            $session->unsWorldpayRealOrderId();
            $session->setQuoteId($session->getWorldpayQuoteId(true));
            $session->setLastSuccessQuoteId($session->getWorldpaySuccessQuoteId(true));
            $this->_redirect('checkout/onepage/success');
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch(Exception $e) {
            $this->_debug('Worldpay error: ' . $e->getMessage());
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * Worldpay return action
     */
    public function cancelAction()
    {
        // set quote to active
        $session = $this->_getCheckout();
        if ($quoteId = $session->getWorldpayQuoteId()) {
            $quote = Mage::getModel('sales/quote')->load($quoteId);
            if ($quote->getId()) {
                $quote->setIsActive(true)->save();
                $session->setQuoteId($quoteId);
            }
        }
        $session->addError(Mage::helper('worldpay')->__('The order has been canceled.'));
        $this->_redirect('checkout/cart');
    }


    /**
     * Checking POST variables.
     * Creating invoice if payment was successfull or cancel order if payment was declined
     */
    protected function _checkReturnedPost()
    {
        // check request type
        if (!$this->getRequest()->isPost()) {
            Mage::throwException('Wrong request type.');
        }

        // validate request ip coming from WorldPay/RBS subnet
        $helper = Mage::helper('core/http');
        if (method_exists($helper, 'getRemoteAddr')) {
            $remoteAddr = $helper->getRemoteAddr();
        } else {
            $request = $this->getRequest()->getServer();
            $remoteAddr = $request['REMOTE_ADDR'];
        }
        if (!preg_match('/\.worldpay\.com$/', gethostbyaddr($remoteAddr))) {
            Mage::throwException('Domain can\'t be validated as WorldPay-Domain.');
        }

        // get request variables
        $request = $this->getRequest()->getPost();
        if (empty($request)) {
            Mage::throwException('Request doesn\'t contain POST elements.');
        }

        // check order id
        if (empty($request['MC_orderid']) || strlen($request['MC_orderid']) > 50) {
            Mage::throwException('Missing or invalid order ID');
        }

        // load order for further validation
        $this->_order = Mage::getModel('sales/order')->loadByIncrementId($request['MC_orderid']);
        if (!$this->_order->getId()) {
            Mage::throwException('Order not found');
        }

        $this->_paymentInst = $this->_order->getPayment()->getMethodInstance();

        // check transaction password
        if ($this->_paymentInst->getConfigData('transaction_password') != $request['callbackPW']) {
            Mage::throwException('Transaction password wrong');
        }

        return $request;
    }

    /**
     * Process success response
     */
    protected function _processSale($request)
    {
        // check transaction amount and currency
        if ($this->_paymentInst->getConfigData('use_store_currency')) {
            $price      = number_format($this->_order->getGrandTotal(),2,'.','');
            $currency   = $this->_order->getOrderCurrencyCode();
        } else {
            $price      = number_format($this->_order->getBaseGrandTotal(),2,'.','');
            $currency   = $this->_order->getBaseCurrencyCode();
        }

        // check transaction amount
        if ($price != $request['authAmount']) {
            Mage::throwException('Transaction currency doesn\'t match.');
        }

        // check transaction currency
        if ($currency != $request['authCurrency']) {
            Mage::throwException('Transaction currency doesn\'t match.');
        }

        // save transaction information
        $this->_order->getPayment()
        	->setTransactionId($request['transId'])
        	->setLastTransId($request['transId'])
        	->setCcAvsStatus($request['AVS'])
        	->setCcType($request['cardType']);

        // save fraud information
        if (!empty($request['wafMerchMessage'])) {
            $additional_data = $this->_order->getPayment()->getAdditionalData();
            $additional_data .= ($additional_data ? "<br/>\n" : '') . $request['wafMerchMessage'];
            $this->_order->getPayment()->setAdditionalData($additional_data);
        }

        switch($request['authMode']) {
            case 'A':
                if ($this->_order->canInvoice()) {
                    $invoice = $this->_order->prepareInvoice();
                    $invoice->register()->capture();
                    Mage::getModel('core/resource_transaction')
                        ->addObject($invoice)
                        ->addObject($invoice->getOrder())
                        ->save();
                }
                $this->_order->addStatusToHistory($this->_paymentInst->getConfigData('order_status'), Mage::helper('worldpay')->__('authorize: Customer returned successfully'));
                break;
            case 'E':
                $this->_order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, $this->_paymentInst->getConfigData('order_status'),  Mage::helper('worldpay')->__('preauthorize: Customer returned successfully'));
                break;
        }

        $this->_order->sendNewOrderEmail();
        $this->_order->setEmailSent(true);

        $this->_order->save();

        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock($this->_successBlockType)
                ->setOrder($this->_order)
                ->toHtml()
        );
    }

    /**
     * Process success response
     */
    protected function _processCancel($request)
    {
        // cancel order
        if ($this->_order->canCancel()) {
            $this->_order->cancel();
            $this->_order->addStatusToHistory(Mage_Sales_Model_Order::STATE_CANCELED, Mage::helper('worldpay')->__('Payment was canceled'));
            $this->_order->save();
        }

        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock($this->_cancelBlockType)
                ->setOrder($this->_order)
                ->toHtml()
        );
    }

    protected function _getPendingPaymentStatus()
    {
        return Mage::helper('worldpay')->getPendingPaymentStatus();
    }

    /**
     * Log debug data to file
     *
     * @param mixed $debugData
     */
    protected function _debug($debugData)
    {
        if (Mage::getStoreConfigFlag('payment/worldpay_cc/debug')) {
            Mage::log($debugData, null, 'payment_worldpay_cc.log', true);
        }
    }
}
