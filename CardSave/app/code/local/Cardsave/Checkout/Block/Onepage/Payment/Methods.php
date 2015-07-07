<?php

/**
 * One page checkout status
 *
 * @category   Mage
 * @category   Mage
 * @package    Mage_Checkout
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Cardsave_Checkout_Block_Onepage_Payment_Methods extends Mage_Checkout_Block_Onepage_Payment_Methods
{
    /**
     * Override the base function - by default the CardSave payment option will be selected
     *
     * @return mixed
     */
    /*public function getSelectedMethodCode()
    {
    	$method = false;
    	$model = Mage::getModel('cardsaveonlinepayments/direct');
    	
        if ($this->getQuote()->getPayment()->getMethod())
        {
            $method = $this->getQuote()->getPayment()->getMethod();
        }
        /*else 
        {
        	// force the current payment to be selected
        	if($model)
        	{
        		$method = 'cardsaveonlinepayments';
        	}
        }*//*
        
        return $method;
    }*/
}
