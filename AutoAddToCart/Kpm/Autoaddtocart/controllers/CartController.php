<?php 	require_once 'Mage/Checkout/controllers/CartController.php';
		require_once 'SpectrumProductProcessor.php'; 
		
	class Kpm_Autoaddtocart_CartController extends Mage_Checkout_CartController {
		public function addAction() {

			Mage::log('debug :  *** addAction');
			
			$_product = $this->processProduct();;
			$cart   = $this->_getCart();
			$tokens = SpectrumProductProcessor::getImageProcessingType($_product);
			
			if (count($tokens) > 0) {
				foreach($tokens as $token) {
					Mage::log('processing token = '.$token);
					if ($token != "" && $token != "None" && $token != "none") {
	 					SpectrumProductProcessor::addProcessingProductToCart($token, $cart);
		            	$this->_getSession()->addSuccess($token." was added to your shopping cart."); 	
					} else {
						Mage::log("SKU : ".$_product->getSku()." requires no image processing");	
					}		
				}
			} else {
				Mage::log("SKU : ".$_product->getSku()." requires no image processing");	
			}
	            		            	
			$cart   = $this->_getCart();
			if ($this->processRedrawingOption($_product)) {   				
				Mage::log("REDRAWING REQUIRED"); 
				$id = Mage::getModel('catalog/product')->getIdBySku("artwork-redrawing-fee");    
				if ($id) {           
					$cart->addProductsByIDs(array($id));
				    $message = $this->__('Artwork redrawing fee was added to your shopping cart.');
	            	$this->_getSession()->addSuccess($message);
				} else {
					Mage::log("Unable to locate artwork redrawing fee product");
				}
	         }

			if ($this->processOption($_product, 
									 "Rush Order Surcharge (If order is required in less than 10 days please apply.)",
									 "Yes Please")) {   
				
				Mage::log("RUSH ORDER SURCHARGE REQUIRED"); 
				$id = Mage::getModel('catalog/product')->getIdBySku("rush-order-surcharge");    
				if ($id) {           
					$cart->addProductsByIDs(array($id));
				    $message = $this->__('Rush order surcharge fee was added to your shopping cart.');
	            	$this->_getSession()->addSuccess($message);
				} else {
					Mage::log("Unable to locate rush order surcharge product");
				}
	         } 
	         
	         if ($this->processOption($_product, "2nd Poll Printing", "Yes Please")) {
	         	Mage::log("2nd poll printing REQUIRED"); 
				$id = Mage::getModel('catalog/product')->getIdBySku("2nd-poll-printing-surcharge");    
				if ($id) {           
					$cart->addProductsByIDs(array($id));
				    $message = $this->__('2nd poll printing fee was added to your shopping cart.');
	            	$this->_getSession()->addSuccess($message);
				} else {
					Mage::log("Unable to locate 2nd poll printing surcharge product");
				}	         	
	         }
	        $cart->removeItem($_product->getId())->save();
			parent::addAction();
		}

	    public function processProduct()
    	{
    		Mage::log("Processing Product");
        	$cart   = $this->_getCart();
        	$params = $this->getRequest()->getParams();
 
            if (isset($params['qty'])) {
                $filter = new Zend_Filter_LocalizedToNormalized(
                    array('locale' => Mage::app()->getLocale()->getLocaleCode())
                );
                $params['qty'] = $filter->filter($params['qty']);
            }

            $_product = $this->_initProduct();
            Mage::log("Product SKU : ".$_product->getSku());
            $related = $this->getRequest()->getParam('related_product');

            $cart->addProduct($_product, $params);
            return $_product;
   		}
		
		private function clearCart($itemId) {
			$cart   = $this->_getCart();
			$cart->removeItem($itemId)->save();
			Mage::log("removed from cart ".$itemId); 

		}
		
		private function processOption($product, $label, $required) {
			//Mage::log("Process Options : ".$label); 

			$items = Mage::getSingleton('checkout/session')->getQuote()->getAllItems();
			$match = false;
			//Mage::log("PRO1 ".count($items));
			foreach ($items as $item) {
				//Mage::log('SKU : '. $item->getSku());	
				if ($product->getSku() == $item->getSku()) {
					//Mage::log('Found product in the cart'. $item->getSku());	
					$productOptions = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct()); 
					//Mage::log($item->getSku()." has ".count($productOptions)." product options");
	
					$loop = 0;
					foreach($productOptions as $option => $arr) {
						//Mage::log("looping ..");
						if ($loop++ == 1) {
							//Mage::log("label". var_dump($arr));
							foreach($arr as $o => $oe) {
								//print("label : ".$oe["label"]);
								if ($oe["label"] == $label) {
									if ($oe["value"] == $required) {	
										//$this->clearCart($item->getProduct()->getId());								
										$match = true;
									}
								}
							}
						}
					}
				}
			}
			return $match;
		}
		
		/*
		 * we are looking for the redrawing label but we only want to match the 1st part of the string
		 * as we may modify the product option to include different pricing going forward so don't want
		 * exact matching.
		 * e.g. we should match
		 * "Professional Redrawing Service (Have your image professionaly finished for best results)"
		 * or "Professional Redrawing Service �19.88"
		 * by matching just "Professional Redrawing Service"
		 */
		private function processRedrawingOption($product) {
			//Mage::log("Process Redrawing Option"); 

			$redrawingOptionSub = "Artwork Redrawing";			
			$required = "Yes Please";
			
			$items = Mage::getSingleton('checkout/session')->getQuote()->getAllItems();
			$match = false;
			//Mage::log("PRO1 ".count($items));
			foreach ($items as $item) {
				Mage::log('processing options for SKU : '. $item->getSku());	
				if ($product->getSku() == $item->getSku()) {
					//Mage::log('Found product in the cart'. $item->getSku());	
					$productOptions = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct()); 
					//Mage::log("product options : ".count($productOptions));
	
					$loop = 0;
					foreach($productOptions as $option => $arr) {
						//Mage::log("looping ..");
						if ($loop++ == 1) {		
							foreach($arr as $o => $oe) {	
								//Mage::log('comparing label : '.$oe["label"]);	
								//$pos = stripos($oe["label"],'Professional');
								//Mage::log('pos:'.$pos);				
								if (stripos($oe["label"],$redrawingOptionSub) !== false) {									
									if ($oe["value"] == $required) {							
										$match = true;
									}									
								}
							}
						}
					}
				}
			}
			return $match;
		}
}?>