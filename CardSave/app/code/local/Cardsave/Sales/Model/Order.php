<?php

class Cardsave_Sales_Model_Order extends Mage_Sales_Model_Order
{
	/*protected function _setState($state, $status = false, $comment = '', $isCustomerNotified = null, $shouldProtectState = false)
    {
        // attempt to set the specified state
        if ($shouldProtectState)
        {
            if ($this->isStateProtected($state))
            {
                Mage::throwException(Mage::helper('sales')->__('The Order State "%s" must not be set manually.', $state));
            }
        }
		
        $this->setData('state', $state);

        // add status history
        if ($status)
        {
            if ($status === true)
            {
                $status = $this->getConfig()->getStateDefaultStatus($state);
            }
            
            $this->setStatus($status);
            $history = $this->addStatusHistoryComment($comment, false); // no sense to set $status again
            $history->setIsCustomerNotified($isCustomerNotified); // for backwards compatibility
        }
        return $this;
    }*/
}