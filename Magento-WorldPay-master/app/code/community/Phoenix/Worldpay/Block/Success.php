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
 * @category   Phoenix
 * @package    Phoenix_Worldpay
 * @copyright  Copyright (c) 2009 Phoenix Medien GmbH & Co. KG (http://www.phoenix-medien.de)
 */

class Phoenix_Worldpay_Block_Success extends Mage_Core_Block_Abstract
{
    protected function _toHtml()
    {
        $successUrl = Mage::getUrl('*/*/success', array('_nosid' => true));

        $html	= '<html>'
        		. '<meta http-equiv="refresh" content="0; URL='.$successUrl.'">'
        		. '<body>'
        		. '<p>' . $this->__('Your payment has been successfully processed by our shop system.') . '</p>'
        		. '<p>' . $this->__('Please click <a href="%s">here</a> if you are not redirected automatically.', $successUrl) . '</p>'
        		. '</body></html>';

        return $html;
    }
}