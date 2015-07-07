<?php

class Cardsave_Cardsaveonlinepayments_Block_Info extends Mage_Payment_Block_Info
{
    /**
     * Init default template for block
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('cardsaveonlinepayments/info.phtml');
    }
    
    public function getCcNumber() 
    {
        return $this->getInfo()->decrypt($this->getInfo()->getCcNumberEnc());
    }
    
    public function getCcCid() 
    {
        return $this->getInfo()->decrypt($this->getInfo()->getCcCidEnc());
    }
    
    /**
     * Retrieve CC expiration date
     *
     * @return Zend_Date
     */
    public function getCcExpDate()
    {
        $date = Mage::app()->getLocale()->date(0);
        $date->setYear($this->getInfo()->getCcExpYear());
        $date->setMonth($this->getInfo()->getCcExpMonth());
        return $date;
    }
}