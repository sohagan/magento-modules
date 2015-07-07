<?php

class Cardsave_Sales_Model_Order_Invoice extends Mage_Sales_Model_Order_Invoice
{
    /**
     * Capture invoice
     *
     * @return Mage_Sales_Model_Order_Invoice
     */
    public function capture()
    {
        $this->getOrder()->getPayment()->capture($this);

        if ($this->getIsPaid())
	    {
	    	$this->pay();
	   	}
        return $this;
    }
    
	public function pay()
    {
    	$nVersion = Mage::getModel('cardsaveonlinepayments/direct')->getVersion();
    	
    	if($nVersion >= 1400)
    	{
	        if ($this->_wasPayCalled)
	        {
	            return $this;
	        }
	        
	        if(!$this->getOrder()->getIsThreeDSecurePending() &&
	        	!$this->getOrder()->getIsHostedPaymentPending())
	        {
		        $this->_wasPayCalled = true;
		
		        $invoiceState = self::STATE_PAID;
		        if ($this->getOrder()->getPayment()->hasForcedState())
		        {
		            $invoiceState = $this->getOrder()->getPayment()->getForcedState();
		        }
		
		        $this->setState($invoiceState);
		
		        $this->getOrder()->getPayment()->pay($this);
		        $this->getOrder()->setTotalPaid(
		            $this->getOrder()->getTotalPaid()+$this->getGrandTotal()
		        );
		        $this->getOrder()->setBaseTotalPaid(
		            $this->getOrder()->getBaseTotalPaid()+$this->getBaseGrandTotal()
		        );
		        Mage::dispatchEvent('sales_order_invoice_pay', array($this->_eventObject=>$this));
	        }
    	}
    	else if($nVersion == 1324 || $nVersion == 1330)
    	{
    		$invoiceState = self::STATE_PAID;
	        if ($this->getOrder()->getPayment()->hasForcedState())
	        {
	            $invoiceState = $this->getOrder()->getPayment()->getForcedState();
	        }
	        $this->setState($invoiceState);
	
	        $this->getOrder()->getPayment()->pay($this);
	        $this->getOrder()->setTotalPaid(
	            $this->getOrder()->getTotalPaid()+$this->getGrandTotal()
	        );
	        $this->getOrder()->setBaseTotalPaid(
	            $this->getOrder()->getBaseTotalPaid()+$this->getBaseGrandTotal()
	        );
	        Mage::dispatchEvent('sales_order_invoice_pay', array($this->_eventObject=>$this));
    	}
        
        return $this;
    }
}