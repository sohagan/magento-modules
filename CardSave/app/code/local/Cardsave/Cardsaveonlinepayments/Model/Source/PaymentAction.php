<?php

class Cardsave_Cardsaveonlinepayments_Model_Source_PaymentAction extends Mage_Paygate_Model_Authorizenet_Source_PaymentAction
{
	public function toOptionArray()
    {
        return array
        (
        	 // override the core class to ONLy allow capture transactions (immediate settlement)
        	  array
            (
            	'value' => Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE,
            	'label' => Mage::helper('paygate')->__('PREAUTH')
            ),
            array
            (
                'value' => Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE_CAPTURE,
                'label' => Mage::helper('paygate')->__('SALE')
            )
        );
    }
}