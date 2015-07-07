<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Sales
 * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
//class Mage_Sales_Block_Order_Totals extends Mage_Core_Block_Template
class Cardsave_Sales_Block_Order_Totals extends Mage_Sales_Block_Order_Totals
{
    /**
     * Initialize order totals array
     *
     * @return Mage_Sales_Block_Order_Totals
     */
    protected function _initTotals()
    {
        $source = $this->getSource();

        $this->_totals = array();
        $this->_totals['subtotal'] = new Varien_Object(array(
            'code'  => 'subtotal',
            'value' => $source->getSubtotal(),
            'label' => $this->__('Subtotal')
        ));


        /**
         * Add shipping
         */
        if (!$source->getIsVirtual() && ((float) $source->getShippingAmount() || $source->getShippingDescription()))
        {
            $this->_totals['shipping'] = new Varien_Object(array(
                'code'  => 'shipping',
                'field' => 'shipping_amount',
                'value' => $this->getSource()->getShippingAmount(),
                'label' => $this->__('Shipping & Handling')
            ));
        }

        /**
         * Add discount
         */
        if (((float)$this->getSource()->getDiscountAmount()) != 0) {
            if ($this->getSource()->getDiscountDescription()) {
                $discountLabel = $this->__('Discount (%s)', $source->getDiscountDescription());
            } else {
                $discountLabel = $this->__('Discount');
            }
            $this->_totals['discount'] = new Varien_Object(array(
                'code'  => 'discount',
                'field' => 'discount_amount',
                'value' => $source->getDiscountAmount(),
                'label' => $discountLabel
            ));
        }

        $this->_totals['grand_total'] = new Varien_Object(array(
            'code'  => 'grand_total',
            'field'  => 'grand_total',
            'strong'=> true,
            'value' => $source->getGrandTotal(),
            'label' => $this->__('Grand Total')
        ));

        /**
         * Base grandtotal
         */
		$takePaymentInStoreBaseCurrency = Mage::getModel('cardsaveonlinepayments/direct')->getConfigData('takePaymentInStoreBaseCurrency');
		 
		if ($this->getOrder()->getPayment()->getMethod() == 'cardsaveonlinepayments') {
			if ($takePaymentInStoreBaseCurrency){
				if ($this->getOrder()->isCurrencyDifferent()) {
					$this->_totals['base_grandtotal'] = new Varien_Object(array(
						'code'  => 'base_grandtotal',
						'value' => $this->getOrder()->formatBasePrice($source->getBaseGrandTotal()),
						'label' => $this->__('Grand Total to be Charged'),
						'is_formated' => true,
					));
				}
			} 
		} else {		 
			if ($this->getOrder()->isCurrencyDifferent()) {
				$this->_totals['base_grandtotal'] = new Varien_Object(array(
					'code'  => 'base_grandtotal',
					'value' => $this->getOrder()->formatBasePrice($source->getBaseGrandTotal()),
					'label' => $this->__('Grand Total to be Charged'),
					'is_formated' => true,
				));
			}
		}
        return $this;
    }    
}
