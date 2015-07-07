<?php
class Cardsave_Cardsaveonlinepayments_Block_Form extends Mage_Payment_Block_Form
{
 	protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('cardsaveonlinepayments/form.phtml');
    }
    
    protected function _getConfig()
    {
        return Mage::getSingleton('payment/config');
    }
    
    /**
     * Returns an array of credit card expiry months
     *
     * @return unknown
     */
    public function getCcMonths()
    {
        $months = $this->getData('cc_months');
        
        if (is_null($months))
        {
        	$months = array();
        	$monthList = $this->_getConfig()->getMonths();
        	
        	$months[0] =  $this->__('Month');
       		for ($nCount = 1; $nCount <= sizeof($monthList); $nCount++)
            {
            	$value = $nCount;
            	if($value < 10)
            	{
            		$value = '0'.$value;
            	}
            	$months[$value] = $monthList[$nCount];
            }
        	
            $this->setData('cc_months', $months);
        }
        
        return $months;
    }

    /**
     * Returns an array of credit card expiry years
     *
     * @return unknown
     */
    public function getCcYears()
    {
        $years = $this->getData('cc_years');
        
        if (is_null($years))
        {
        	$years = array();
        	$first = date("Y");
        
        	$years = array($this->__('Year'));
	        for ($index = 0; $index < 10; $index++)
	        {
	            $year = $first + $index;
	            $yearPartial = substr($year, 2);
	            $years[$yearPartial] = $year;
	        }
            $this->setData('cc_years', $years);
        }
        
        return $years;
    }
    
    /**
     * Returns an array credit card start months
     *
     * @return unknown
     */
	public function getCcStartMonths()
    {
    	$months = $this->getData('cc_ss_start_month');
        
        if (is_null($months))
        {
        	$months = array();
            $monthList = $this->_getConfig()->getMonths();
            
            for ($nCount = 1; $nCount <= sizeof($monthList); $nCount++)
            {
            	$value = $nCount;
            	if($value < 10)
            	{
            		$value = '0'.$value;
            	}
            	$months[$value] = $monthList[$nCount];
            }
            $this->setData('cc_ss_start_month', $months);
        }
        
        return $months;
    }
    
    /**
     * Returns an array of credit card start years
     *
     * @return unknown
     */
    public function getCcStartYears()
    {
    	$years = $this->getData('cc_start_years');
    	
    	if(is_null($years))
    	{
	    	$years = array();
	        $first = date("Y");
	
	        for ($index = 0; $index < 10; $index++)
	        {
	            $year = $first - $index;
	            $yearPartial = substr($year, 2);
	            $years[$yearPartial] = $year;
	        }
	        $this->setData('cc_start_years', $years);
    	}

        return $years;
    }
}