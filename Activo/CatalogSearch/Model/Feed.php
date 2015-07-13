<?php
/**
 * Activo Extensions
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Activo Commercial License
 * that is available through the world-wide-web at this URL:
 * http://extensions.activo.com/license_professional
 *
 * @copyright   Copyright (c) 2013 Activo Extensions (http://extensions.activo.com)
 * @license     OSL 3.0
 */

//http://www.nicksays.co.uk/2009/05/magento-custom-admin-notifications/
class Activo_CatalogSearch_Model_Feed extends Mage_AdminNotification_Model_Feed
{   
    public function getFeedUrl()
    {
        preg_match('/(.*)_Model_Feed/', get_class($this), $matches);
        $module = isset($matches[1]) ? $matches[1] : "";
        
        if (is_null($this->_feedUrl)) {
            $this->_feedUrl = 'http://extensions.activo.com/adminrss.php?s='
                    .urlencode(Mage::getStoreConfig(Mage_Core_Model_Url::XML_PATH_UNSECURE_URL))
                    .'&m='.  $module;
//            Mage::log($this->_feedUrl);
        }
        return $this->_feedUrl;
    }

    public function observe() 
    {
        $model  = Mage::getModel('catalogsearch2/feed');
        $model->checkUpdate();
    }
    
//    public function getFrequency()
//    {
//        return 1;
//    }

}
