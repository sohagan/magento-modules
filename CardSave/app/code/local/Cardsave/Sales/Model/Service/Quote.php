<?php

class Cardsave_Sales_Model_Service_Quote extends Mage_Sales_Model_Service_Quote
{
    public function submitOrder()
    {
    	$nVersion = Mage::getModel('cardsaveonlinepayments/direct')->getVersion();
    	
    	if($nVersion >= 1410)
    	{
	        if ($this->_quote->getPayment()->getMethodInstance()->getCode() != 'cardsaveonlinepayments')
	        {
	            return parent::submitOrder();
	        }
	        
	        $this->_deleteNominalItems();
	        $this->_validate();
	        $quote = $this->_quote;
	        $isVirtual = $quote->isVirtual();
	
	        $transaction = Mage::getModel('core/resource_transaction');
	        if ($quote->getCustomerId())
	        {
	            $transaction->addObject($quote->getCustomer());
	        }
	        $transaction->addObject($quote);
	
	        $quote->reserveOrderId();
	        if ($isVirtual)
	        {
	            $order = $this->_convertor->addressToOrder($quote->getBillingAddress());
	        }
	        else
	        {
	            $order = $this->_convertor->addressToOrder($quote->getShippingAddress());
	        }
	        $order->setBillingAddress($this->_convertor->addressToOrderAddress($quote->getBillingAddress()));
	        
	        if (!$isVirtual)
	        {
	            $order->setShippingAddress($this->_convertor->addressToOrderAddress($quote->getShippingAddress()));
	        }
	        $order->setPayment($this->_convertor->paymentToOrderPayment($quote->getPayment()));
	
	        foreach ($this->_orderData as $key => $value)
	        {
	            $order->setData($key, $value);
	        }
	
	        foreach ($quote->getAllItems() as $item)
	        {
	            $orderItem = $this->_convertor->itemToOrderItem($item);
	            if ($item->getParentItem())
	            {
	                $orderItem->setParentItem($order->getItemByQuoteItemId($item->getParentItem()->getId()));
	            }
	            $order->addItem($orderItem);
	        }
	        // make sure the customer can still use the quote if payment is failed
	        //$quote->setIsActive(false);
	
	        $transaction->addObject($order);
	        $transaction->addCommitCallback(array($order, 'place'));
	        $transaction->addCommitCallback(array($order, 'save'));
	
	        /**
	         * We can use configuration data for declare new order status
	         */
	        Mage::dispatchEvent('checkout_type_onepage_save_order', array('order'=>$order, 'quote'=>$quote));
	        Mage::dispatchEvent('sales_model_service_quote_submit_before', array('order'=>$order, 'quote'=>$quote));
	        try
	        {
	            $transaction->save();
	            Mage::dispatchEvent('sales_model_service_quote_submit_success', array('order'=>$order, 'quote'=>$quote));
	
	            // need to store the orderID in the session for the callback from an external page
	            Mage::getSingleton('checkout/session')->setCardsaveonlinepaymentsOrderId($order->getId());
	
	        }
	        catch (Exception $e)
	        {
	            Mage::logException($e);
	            Mage::dispatchEvent('sales_model_service_quote_submit_failure', array('order'=>$order, 'quote'=>$quote));
	            throw $e;
	        }
	        Mage::dispatchEvent('sales_model_service_quote_submit_after', array('order'=>$order, 'quote'=>$quote));
	        $this->_order = $order;
	        
	        return $order;
    	}
    }
}
