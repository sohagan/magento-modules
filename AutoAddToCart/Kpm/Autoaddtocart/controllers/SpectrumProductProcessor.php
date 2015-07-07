<?php 
class SpectrumProductProcessor {
	
	public static function getImageProcessingType($product) {
		$attribute = $product->getResource()->getAttribute("image_processing_type");
			
		$frontend = $attribute->getFrontend();			
		$optionId = $product->getImageProcessingType();			
		$value = $frontend->getOption( $optionId );
		Mage::log('debug :  *** image processing type:'.$value);  

		$tokens = explode(':', $value);

 		return $tokens;		
	}
	
	public static function addProcessingProductToCart($value, $cart) {
		$store = SpectrumProductProcessor::getStoreType();
		
		Mage::log('debug :  *** addProcessingProductToCart :'.$value. " in the :".$store." store" );  
		if ($value == 'Colour Printing Origination Fee') {    
			$id = Mage::getModel('catalog/product')->getIdBySku("colour-printing-origination-fee");    
			if ($id) {           
				$cart->addProductsByIDs(array($id));
         	} 
		} else if ($value == 'Artwork Redrawing Fee') {       
			$id = Mage::getModel('catalog/product')->getIdBySku("artwork-redrawing-fee");    
			if ($id) {   
				$cart->addProductsByIDs(array($id));
            } 
		} else if ($value == 'Pewter Tooling') {       
			$id = Mage::getModel('catalog/product')->getIdBySku("pewter-tooling-fee");    
			if ($id) {   
				$cart->addProductsByIDs(array($id));
            } 
		} else if ($value == 'Foil Block Fee') {       
			$id = Mage::getModel('catalog/product')->getIdBySku("foil-block-fee");    
			if ($id) {   
				$cart->addProductsByIDs(array($id));
         	} 
		} else if ($value == 'Stitching Origination Fee') {       
			$id = Mage::getModel('catalog/product')->getIdBySku("stitching-origination-fee");       
			if ($id) {   
				$cart->addProductsByIDs(array($id));
         	} 
		} else if ($value == 'Silk Screen Printing Fee') {       
			$id = Mage::getModel('catalog/product')->getIdBySku("silk-screen-printing-fee");    
			if ($id) {  
				$cart->addProductsByIDs(array($id));
			} 
		} else if ($value == 'Card Header Fee') {       
			$id = Mage::getModel('catalog/product')->getIdBySku("card-header-fee");    
			if ($id) {  
				$cart->addProductsByIDs(array($id));
			}
		} else if ($value == 'Tin and Tee Printing Fee') {       
			$id = Mage::getModel('catalog/product')->getIdBySku("tin-and-tee-printing-fee");    
			if ($id) {  
				$cart->addProductsByIDs(array($id));
			}
		} else if ($value == 'Hand Painting Fee') {       
			$id = Mage::getModel('catalog/product')->getIdBySku("hand-painting-fee");    
			if ($id) {  
				$cart->addProductsByIDs(array($id));
			}
		} else if ($value == '1 Side Bag Tag Printing') {       
			$id = Mage::getModel('catalog/product')->getIdBySku("1-side-bag-tag-printing-fee");    
			if ($id) {  
				$cart->addProductsByIDs(array($id));
			}	
		} else if ($value == '2 Sided Bag Tag Printing') {       
			$id = Mage::getModel('catalog/product')->getIdBySku("2-side-bag-tag-printing-fee");    
			if ($id) {  
				$cart->addProductsByIDs(array($id));
			}	
		} else {
			Mage::log('unknown image processing type '.$value);	
		}		
	}
	
	public static function getStoreType() {
		$storeName = Mage::app()->getStore()->getName();
		if ($storeName == "Corporate Store View") return "corporate";
		if ($storeName == "Society Store View") return "society";
		if ($storeName == "Individual Store View") return "individual";
		if ($storeName == "Trade Store View") return "trade";
	}
		
}

?>