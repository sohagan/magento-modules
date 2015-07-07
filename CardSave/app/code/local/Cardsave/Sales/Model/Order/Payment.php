<?php

class Cardsave_Sales_Model_Order_Payment extends Mage_Sales_Model_Order_Payment
{	
    /**
     * Authorize or authorize and capture payment on gateway, if applicable
     * This method is supposed to be called only when order is placed
     *
     * @return Mage_Sales_Model_Order_Payment
     */
    public function place()
   {
	
    	Mage::dispatchEvent('sales_order_payment_place_start', array('payment' => $this));
        $order = $this->getOrder();

        $this->setAmountOrdered($order->getTotalDue());
        $this->setBaseAmountOrdered($order->getBaseTotalDue());
        $this->setShippingAmount($order->getShippingAmount());
        $this->setBaseShippingAmount($order->getBaseShippingAmount());

        $methodInstance = $this->getMethodInstance();
        $methodInstance->setStore($order->getStoreId());

        $orderState = Mage_Sales_Model_Order::STATE_NEW;
        $orderStatus= false;

        $stateObject = new Varien_Object();

        /**
         * Do order payment validation on payment method level
         */
        $methodInstance->validate();
        $action = $methodInstance->getConfigPaymentAction();
        if ($action) {
            if ($methodInstance->isInitializeNeeded()) {
                /**
                 * For method initialization we have to use original config value for payment action
                 */
                $methodInstance->initialize($methodInstance->getConfigData('payment_action'), $stateObject);
            } else {
                $orderState = Mage_Sales_Model_Order::STATE_PROCESSING;
                switch ($action) {
                    case Mage_Payment_Model_Method_Abstract::ACTION_ORDER:
                        $this->_order($order->getBaseTotalDue());
                        break;
                    case Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE:
                        $this->_authorize(true, $order->getBaseTotalDue()); // base amount will be set inside
                        $this->setAmountAuthorized($order->getTotalDue());
                        break;
                    case Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE:
                        $this->setAmountAuthorized($order->getTotalDue());
                        $this->setBaseAmountAuthorized($order->getBaseTotalDue());
                        $this->capture(null);
                        break;
                    default:
                        break;
                }
            }
        }

        $this->_createBillingAgreement();

        $orderIsNotified = null;
        
        if ($order->getIsThreeDSecurePending()) {
			$orderState = 'pending_payment';
			$orderStatus = 'csv_pending_threed_secure';
			$message = '3D Secure authentication need to be completed';
			$orderIsNotified = false;
		} else if ($order->getIsHostedPaymentPending()) {
			$order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true)->save();
			$orderStateHelper = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
			$orderState = 'pending_payment';
			$orderStatus = 'csv_pending_hosted_payment';
			$message = 'Hosted Payment need to be completed';
		} else if ($order->getPayment()->getMethodInstance()->getCode() == 'cardsaveonlinepayments') {
			$orderState = 'processing';
			if(Mage::getModel('cardsaveonlinepayments/direct')->getConfigData('payment_action') == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE_CAPTURE) {
				$orderStatus = "csv_paid";
			} else if (Mage::getModel('cardsaveonlinepayments/direct')->getConfigData('payment_action') == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE) {
				$orderStatus = "csv_preauth";
			}			
			$message = '';
			$orderIsNotified = false;
		} else if ($stateObject->getState() && $stateObject->getStatus()) {
            $orderState      = $stateObject->getState();
            $orderStatus     = $stateObject->getStatus();
            $orderIsNotified = $stateObject->getIsNotified();		
		} else {
			$orderIsNotified = false;
            $orderStatus = $methodInstance->getConfigData('order_status');
            if (!$orderStatus) {
                $orderStatus = $order->getConfig()->getStateDefaultStatus($orderState);
            }
        }
        $isCustomerNotified = (null !== $orderIsNotified) ? $orderIsNotified : $order->getCustomerNoteNotify();
        $ordernote = $order->getCustomerNote();
		if ($ordernote) {
			$order->addStatusToHistory($order->getStatus(), $ordernote, $isCustomerNotified);
		}		

        // add message if order was put into review during authorization or capture
        if ($order->getState() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW) {
            if ($message) {
                $order->addStatusToHistory($order->getStatus(), $message, $isCustomerNotified);
            }
        }
        // add message to history if order state already declared
        elseif ($order->getState() && ($orderStatus !== $order->getStatus() || $message)) {
            $order->setState($orderState, $orderStatus, $message, $isCustomerNotified);
        }
        // set order state
        elseif (($order->getState() != $orderState) || ($order->getStatus() != $orderStatus) || $message) {
            $order->setState($orderState, $orderStatus, $message, $isCustomerNotified);
        }

        Mage::dispatchEvent('sales_order_payment_place_end', array('payment' => $this));

        return $this;	
    }
}