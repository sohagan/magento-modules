<?php

class Cardsave_Cardsaveonlinepayments_Model_Source_OrderStatus
{
	public function toOptionArray()
    {
        return array(
        	 // override the order status and ONLY offer "pending" by default 
            array(
                'value' => 'processing',
                'label' => Mage::helper('cardsaveonlinepayments')->__('Processing')
            ),
        );
    }
}