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

class Phoenix_Worldpay_Model_Config_Backend_Instid extends Mage_Core_Model_Config_Data
{
    /**
     * Verify installation id in Worldpay registration system to reduce configuration failures (experimental)
     *
     * @return Phoenix_Worldpay_Model_Config_Backend_Instid
     */
    protected function _beforeSave()
    {
        try {
            if ($this->getValue()) {
                $client = new Varien_Http_Client();
                $client->setUri((string)Mage::getConfig()->getNode('phoenix/worldpay/verify_url'))
                    ->setConfig(array('timeout'=>10,))
                    ->setHeaders('accept-encoding', '')
                    ->setParameterPost('inst_id', $this->getValue())
                    ->setMethod(Zend_Http_Client::POST);
                $response = $client->request();
//                $responseBody = $response->getBody();
//                if (empty($responseBody) || $responseBody != 'VERIFIED') {
                    // verification failed. throw error message (not implemented yet).
//                }

                // okay, inst_id verified. continue saving.
            }
        } catch (Exception $e) {
            // verification system unavailable. no further action.
        }

        return parent::_beforeSave();
    }
}
