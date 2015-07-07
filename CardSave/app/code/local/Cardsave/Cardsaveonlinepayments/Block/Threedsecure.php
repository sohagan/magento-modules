<?php 

class Cardsave_Cardsaveonlinepayments_Block_Threedsecure extends Mage_Core_Block_Abstract
{
	/**
	 * Build the 3D Secure form to be submitted to the redirect 3D Secure authorization page
	 * 
	 */
	protected function _toHtml()
    {
    	$mode = Mage::getModel('cardsaveonlinepayments/direct')->getConfigData('mode');
    	
    	if($mode == Cardsave_Cardsaveonlinepayments_Model_Source_PaymentMode::PAYMENT_MODE_TRANSPARENT_REDIRECT)
    	{
    		$szTermURL = 'cardsaveonlinepayments/payment/callbacktransparentredirect';
    	}
    	else
    	{
    		$szTermURL = 'cardsaveonlinepayments/payment/callback3d';
    	}
    	
        $form = new Varien_Data_Form();
        $form->setAction(Mage::getSingleton('checkout/session')->getAcsurl())
            ->setId('ThreeDSecureForm')
            ->setName('ThreeDSecureForm')
            ->setMethod('POST')
            ->setUseContainer(true);

        $form->addField("PaReq", 'hidden', array('name'=>"PaReq", 'value'=>Mage::getSingleton('checkout/session')->getPareq()));
        $form->addField("MD", 'hidden', array('name'=>"MD", 'value'=>Mage::getSingleton('checkout/session')->getMd()));
        $form->addField("TermUrl", 'hidden', array('name'=>"TermUrl", 'value'=>Mage::getUrl($szTermURL, array('_secure' => true))));
        
        $html = '<html><body>';
        $html.= $this->__('You will be redirected to a 3D secure form in a few seconds.');
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("ThreeDSecureForm").submit();</script>';
        $html.= '</body></html>';
        
        // reset the 3DS session values
        Mage::getSingleton('checkout/session')->setMd(null)
        										->setAcsurl(null)
		  										->setPareq(null);

        return $html;
    }
}