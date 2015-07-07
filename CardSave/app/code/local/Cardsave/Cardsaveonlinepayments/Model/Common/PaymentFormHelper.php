<?php

	Mage::log('current wd'. getcwd());

	if (!defined('COMPILER_INCLUDE_PATH')) {
	//	require_once "app/code/local/Cardsave/Cardsaveonlinepayments/Model/Source/HashMethod.php";
		require_once getcwd()."/spectrum-magento-modules/current/CardSave/app/code/local/Cardsave/Cardsaveonlinepayments/Model/Source/HashMethod.php";
	} else {
		include_once ("Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod.php");
	}
	
	class CSV_ListItemList
	{
		private $m_lilListItemList;
				
		public function getCount()
		{
			return count($this->m_lilListItemList);
		}
		
		public function getAt($nIndex)
		{
			if ($nIndex < 0 ||
				$nIndex >= count($this->m_lilListItemList))
			{
				throw new Exception('Array index out of bounds');
			}
				
			return $this->m_lilListItemList[$nIndex];
		}
		
		public function add($szName, $szValue, $boIsSelected)
		{
			$liListItem = new CSV_ListItem($szName, $szValue, $boIsSelected);

			$this->m_lilListItemList[] = $liListItem;
		}

		public function toString()
		{
			$szReturnString = "";

			for ($nCount = 0; $nCount < count($this->m_lilListItemList); $nCount++)
			{
				$liListItem = $this->m_lilListItemList[$nCount];
				
				$szReturnString = $szReturnString."<option";

				if ($liListItem->getValue() != null &&
					$liListItem->getValue() != "")
				{
					$szReturnString = $szReturnString." value=\"".$liListItem->getValue()."\"";
				}

				if ($liListItem->getIsSelected() == true)
				{
					$szReturnString = $szReturnString." selected=\"selected\"";	
				}

				$szReturnString = $szReturnString.">".$liListItem->getName()."</option>\n";
			}

			return ($szReturnString);
		}

		//constructor
		public function __construct()
		{
	        $this->m_lilListItemList = array();
		}
	}

	class CSV_ListItem
	{
		private $m_szName;
	   	private $m_szValue;
	    private $m_boIsSelected;
	    
	    //public properties
	    public function getName()
	    {
	    	return $this->m_szName;
	    }
	    
	    public function getValue()
	    {
	    	return $this->m_szValue;
	    }
	   
	    public function getIsSelected()
	    {
	    	return $this->m_boIsSelected;
	    }
	   	    
	    //constructor
	    public function __construct($szName, $szValue, $boIsSelected)
	    {
	    	$this->m_szName = $szName;
	    	$this->m_szValue = $szValue;
	    	$this->m_boIsSelected = $boIsSelected;
	    }
	}

	class CSV_PaymentFormHelper
	{
		/**
		 * Hash mechanism for hosted payment form trasaction
		 *
		 * @param unknown_type $szMerchantID
		 * @param unknown_type $szPassword
		 * @param unknown_type $hmHashMethod
		 * @param unknown_type $szPreSharedKey
		 * @param unknown_type $nAmount
		 * @param unknown_type $nCurrencyCode
		 * @param unknown_type $szOrderID
		 * @param unknown_type $szTransactionType
		 * @param unknown_type $szTransactionDateTime
		 * @param unknown_type $szCallbackURL
		 * @param unknown_type $szOrderDescription
		 * @param unknown_type $szCustomerName
		 * @param unknown_type $szAddress1
		 * @param unknown_type $szAddress2
		 * @param unknown_type $szAddress3
		 * @param unknown_type $szAddress4
		 * @param unknown_type $szCity
		 * @param unknown_type $szState
		 * @param unknown_type $szPostCode
		 * @param unknown_type $nCountryCode
		 * @param unknown_type $boCV2Mandatory
		 * @param unknown_type $boAddress1Mandatory
		 * @param unknown_type $boCityMandatory
		 * @param unknown_type $boPostCodeMandatory
		 * @param unknown_type $boStateMandatory
		 * @param unknown_type $boCountryMandatory
		 * @param unknown_type $rdmResultdeliveryMethod 
		 * @param unknown_type $szServerResultURL
		 * @param unknown_type $boPaymentFormDisplaysResult
		 * @return unknown
		 */
		public static function calculateHashDigest($szMerchantID, $szPassword, $hmHashMethod, $szPreSharedKey, $nAmount, $nCurrencyCode, $szOrderID, $szTransactionType, $szTransactionDateTime, $szCallbackURL, $szOrderDescription, $szCustomerName, $szAddress1, $szAddress2, $szAddress3, $szAddress4, $szCity, $szState, $szPostCode, $nCountryCode, $boCV2Mandatory, $boAddress1Mandatory, $boCityMandatory, $boPostCodeMandatory, $boStateMandatory, $boCountryMandatory, $rdmResultdeliveryMethod, $szServerResultURL, $boPaymentFormDisplaysResult, $szServerResultURLCookieVariables, $szServerResultURLFormVariables, $szServerResultURLQueryStringVariables)
		{
			$szHashDigest = '';
			$szStringBeforeHash;
			
			$szStringBeforeHash = 'MerchantID='.$szMerchantID.'&'.
									'Password='.$szPassword.'&'.
									'Amount='.$nAmount.'&'.
									'CurrencyCode='.$nCurrencyCode.'&'.
									'OrderID='.$szOrderID.'&'.
									'TransactionType='.$szTransactionType.'&'.
									'TransactionDateTime='.$szTransactionDateTime.'&'.
									'CallbackURL='.$szCallbackURL.'&'.
									'OrderDescription='.$szOrderDescription.'&'.
									'CustomerName='.$szCustomerName.'&'.
									'Address1='.$szAddress1.'&'.
									'Address2='.$szAddress2.'&'.
									'Address3='.$szAddress3.'&'.
									'Address4='.$szAddress4.'&'.
									'City='.$szCity.'&'.
									'State='.$szState.'&'.
									'PostCode='.$szPostCode.'&'.
									'CountryCode='.$nCountryCode.'&'.
									'CV2Mandatory='.$boCV2Mandatory.'&'.
									'Address1Mandatory='.$boAddress1Mandatory.'&'.
									'CityMandatory='.$boCityMandatory.'&'.
									'PostCodeMandatory='.$boPostCodeMandatory.'&'.
									'StateMandatory='.$boStateMandatory.'&'.
									'CountryMandatory='.$boCountryMandatory.'&'.
									'ResultDeliveryMethod='.$rdmResultdeliveryMethod.'&'.
									'ServerResultURL='.$szServerResultURL.'&'.
									'PaymentFormDisplaysResult='.$boPaymentFormDisplaysResult.'&'.
									'ServerResultURLCookieVariables='.$szServerResultURLCookieVariables.'&'.
									'ServerResultURLFormVariables='.$szServerResultURLFormVariables.'&'.
									'ServerResultURLQueryStringVariables='.$szServerResultURLQueryStringVariables;
			
			if ($hmHashMethod == Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_MD5 ||
				$hmHashMethod == Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_SHA1)
			{
				$szStringBeforeHash = 'PreSharedKey='.$szPreSharedKey.'&'.$szStringBeforeHash;
			}
			
			$szHashDigest = self::_hashCalculator($hmHashMethod, $szPreSharedKey, $szStringBeforeHash);
			
			return $szHashDigest;
		}
		
		/**
		 * Hash mechanism for transparent redirect trasaction
		 *
		 * @param unknown_type $szMerchantID
		 * @param unknown_type $szPassword
		 * @param unknown_type $hmHashMethod
		 * @param unknown_type $szPreSharedKey
		 * @param unknown_type $nAmount
		 * @param unknown_type $nCurrencyCode
		 * @param unknown_type $szOrderID
		 * @param unknown_type $szTransactionType
		 * @param unknown_type $szTransactionDateTime
		 * @param unknown_type $szCallbackURL
		 * @param unknown_type $szOrderDescription
		 * @return unknown
		 */
		public static function calculateTransparentRedirectHashDigest($szMerchantID, $szPassword, $hmHashMethod, $szPreSharedKey, $nAmount, $nCurrencyCode, $szOrderID, $szTransactionType, $szTransactionDateTime, $szCallbackURL, $szOrderDescription)
		{
			$szHashDigest = '';
			$szStringBeforeHash;
			
			$szStringBeforeHash = 'MerchantID='.$szMerchantID.'&'.
									'Password='.$szPassword.'&'.
									'Amount='.$nAmount.'&'.
									'CurrencyCode='.$nCurrencyCode.'&'.
									'OrderID='.$szOrderID.'&'.
									'TransactionType='.$szTransactionType.'&'.
									'TransactionDateTime='.$szTransactionDateTime.'&'.
									'CallbackURL='.$szCallbackURL.'&'.
									'OrderDescription='.$szOrderDescription;
			
			if ($hmHashMethod == Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_MD5 ||
				$hmHashMethod == Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_SHA1)
			{
				$szStringBeforeHash = 'PreSharedKey='.$szPreSharedKey.'&'.$szStringBeforeHash;
			}
			
			$szHashDigest = self::_hashCalculator($hmHashMethod, $szPreSharedKey, $szStringBeforeHash);
			
			return $szHashDigest;
		}
		
		/**
		 * Hash mechanism for calculating the hash digest for the post 3D Secure Authentication in the transparent redirect payment mode
		 *
		 * @param unknown_type $szMerchantID
		 * @param unknown_type $szPassword
		 * @param unknown_type $hmHashMethod
		 * @param unknown_type $szPreSharedKey
		 * @param unknown_type $szPaRES
		 * @param unknown_type $szCrossReference
		 * @param unknown_type $szTransactionDateTime
		 * @param unknown_type $szCallbackURL
		 * @return unknown
		 */
		public static function calculatePostThreeDSecureAuthenticationHashDigest($szMerchantID, $szPassword, $hmHashMethod, $szPreSharedKey, $szPaRES, $szCrossReference, $szTransactionDateTime, $szCallbackURL)
		{
			$szHashDigest = '';
			$szStringBeforeHash;
			
			$szStringBeforeHash = 'MerchantID='.$szMerchantID.'&'.
									'Password='.$szPassword.'&'.
									'CrossReference='.$szCrossReference.'&'.
									'TransactionDateTime='.$szTransactionDateTime.'&'.
									'CallbackURL='.$szCallbackURL.'&'.
									'PaRES='.$szPaRES;
									
			
			if ($hmHashMethod == Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_MD5 ||
				$hmHashMethod == Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_SHA1)
			{
				$szStringBeforeHash = 'PreSharedKey='.$szPreSharedKey.'&'.$szStringBeforeHash;
			}
			
			$szHashDigest = self::_hashCalculator($hmHashMethod, $szPreSharedKey, $szStringBeforeHash);
			
			return $szHashDigest;
		}
		
		/**
		 * Private hash calculator for hashing the raw string
		 *
		 * @param unknown_type $hmHashMethod
		 * @param unknown_type $szPreSharedKey
		 * @param unknown_type $szStringBeforeHash
		 * @return unknown
		 */
		private static function _hashCalculator($hmHashMethod, $szPreSharedKey, $szStringBeforeHash)
		{
			$szHashDigest = '';
			
			switch ($hmHashMethod)
			{
				case Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_MD5:
					$szHashDigest = md5($szStringBeforeHash);
					break;
				case Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_SHA1:
					$szHashDigest = sha1($szStringBeforeHash);
					break;
				case Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_HMACMD5:
					$szHashDigest = hash_hmac('md5', $szStringBeforeHash, $szPreSharedKey);
					break;
				case Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_HMACSHA1:
					$szHashDigest = hash_hmac('sha1', $szStringBeforeHash, $szPreSharedKey);
					break;
				default:
					throw new Exception('Invalid hash method used for hash digest calculation: '.$hmHashMethod);
					break;
			}
			
			//$szHashDigest = strtoupper($szHashDigest);
			
			return $szHashDigest;
		}
		
		/**
		 * Hash validator mechanism for hosted payment form transaction
		 *
		 * @param unknown_type $szHashDigest
		 * @param unknown_type $szMerchantID
		 * @param unknown_type $szStatusCode
		 * @param unknown_type $szMessage
		 * @param unknown_type $szPreviousStatusCode
		 * @param unknown_type $szPreviousMessage
		 * @param unknown_type $szCrossReference
		 * @param unknown_type $szAmount
		 * @param unknown_type $szCurrencyCode
		 * @param unknown_type $szOrderID
		 * @param unknown_type $szTransactionType
		 * @param unknown_type $szTransactionDateTime
		 * @param unknown_type $szOrderDescription
		 * @param unknown_type $szCustomerName
		 * @param unknown_type $szAddress1
		 * @param unknown_type $szAddress2
		 * @param unknown_type $szAddress3
		 * @param unknown_type $szAddress4
		 * @param unknown_type $szCity
		 * @param unknown_type $szState
		 * @param unknown_type $szPostCode
		 * @param unknown_type $szCountryCode
		 * @return unknown
		 */
		public static function compareHostedPaymentFormHashDigest($formVariables, $szPassword, $hmHashMethod, $szPreSharedKey)
		{
			$boMatch = false;
			$szCalculatedHashDigest;
			$szStringBeforeHash;
			
			$szStringBeforeHash = 'MerchantID='.$formVariables['MerchantID'].'&'.
									'Password='.$szPassword.'&'.
									'StatusCode='.$formVariables['StatusCode'].'&'.
									'Message='.$formVariables['Message'].'&'.
									'PreviousStatusCode='.$formVariables['PreviousStatusCode'].'&'.
									'PreviousMessage='.$formVariables['PreviousMessage'].'&'.
									'CrossReference='.$formVariables['CrossReference'].'&'.
									'Amount='.$formVariables['Amount'].'&'.
									'CurrencyCode='.$formVariables['CurrencyCode'].'&'.
									'OrderID='.$formVariables['OrderID'].'&'.
									'TransactionType='.$formVariables['TransactionType'].'&'.
									'TransactionDateTime='.$formVariables['TransactionDateTime'].'&'.
									'OrderDescription='.$formVariables['OrderDescription'].'&'.
									'CustomerName='.$formVariables['CustomerName'].'&'.
									'Address1='.$formVariables['Address1'].'&'.
									'Address2='.$formVariables['Address2'].'&'.
									'Address3='.$formVariables['Address3'].'&'.
									'Address4='.$formVariables['Address4'].'&'.
									'City='.$formVariables['City'].'&'.
									'State='.$formVariables['State'].'&'.
									'PostCode='.$formVariables['PostCode'].'&'.
									'CountryCode='.$formVariables['CountryCode'];
			
			//echo $szStringBeforeHash;
			
			if ($hmHashMethod == Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_MD5 ||
				$hmHashMethod == Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_SHA1)
			{
				$szStringBeforeHash = 'PreSharedKey='.$szPreSharedKey.'&'.$szStringBeforeHash;
			}
			
			$szCalculatedHashDigest = self::_hashCalculator($hmHashMethod, $szPreSharedKey, $szStringBeforeHash);
			if(strtoupper($formVariables['HashDigest']) == strtoupper($szCalculatedHashDigest))
			{
				$boMatch = true;
			}
			
			return $boMatch;
		}
		
		/**
		 * Hash validator mechanism for the incoming payment complete hash in transparent redirect payment mode
		 *
		 * @param unknown_type $formVariables
		 * @param unknown_type $szPassword
		 * @param unknown_type $hmHashMethod
		 * @param unknown_type $szPreSharedKey
		 * @return unknown
		 */
		public static function comparePaymentCompleteHashDigest($formVariables, $szPassword, $hmHashMethod, $szPreSharedKey)
		{
			$boMatch = false;
			$szCalculatedHashDigest;
			$szStringBeforeHash;
			
			$szStringBeforeHash = 'MerchantID='.$formVariables['MerchantID'].'&'.
									'Password='.$szPassword.'&'.
									'StatusCode='.$formVariables['StatusCode'].'&'.
									'Message='.$formVariables['Message'].'&'.
									'PreviousStatusCode='.$formVariables['PreviousStatusCode'].'&'.
									'PreviousMessage='.$formVariables['PreviousMessage'].'&'.
									'CrossReference='.$formVariables['CrossReference'].'&'.
									'AddressNumericCheckResult='.$formVariables['AddressNumericCheckResult'].'&'.
									'PostCodeCheckResult='.$formVariables['PostCodeCheckResult'].'&'.
									'CV2CheckResult='.$formVariables['CV2CheckResult'].'&'.
									'ThreeDSecureAuthenticationCheckResult='.$formVariables['ThreeDSecureAuthenticationCheckResult'].'&'.
									'CardType='.$formVariables['CardType'].'&'.
									'CardClass='.$formVariables['CardClass'].'&'.
									'CardIssuer='.$formVariables['CardIssuer'].'&'.
									'CardIssuerCountryCode='.$formVariables['CardIssuerCountryCode'].'&'.
									'Amount='.$formVariables['Amount'].'&'.
									'CurrencyCode='.$formVariables['CurrencyCode'].'&'.
									'OrderID='.$formVariables['OrderID'].'&'.
									'TransactionType='.$formVariables['TransactionType'].'&'.
									'TransactionDateTime='.$formVariables['TransactionDateTime'].'&'.
									'OrderDescription='.$formVariables['OrderDescription'].'&'.
									'Address1='.$formVariables['Address1'].'&'.
									'Address2='.$formVariables['Address2'].'&'.
									'Address3='.$formVariables['Address3'].'&'.
									'Address4='.$formVariables['Address4'].'&'.
									'City='.$formVariables['City'].'&'.
									'State='.$formVariables['State'].'&'.
									'PostCode='.$formVariables['PostCode'].'&'.
									'CountryCode='.$formVariables['CountryCode'].'&'.																
									'EmailAddress='.$formVariables['EmailAddress'].'&'.																
									'PhoneNumber='.$formVariables['PhoneNumber'];																
			
			if ($hmHashMethod == Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_MD5 ||
				$hmHashMethod == Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_SHA1)
			{
				$szStringBeforeHash = 'PreSharedKey='.$szPreSharedKey.'&'.$szStringBeforeHash;
			}
			
			$szCalculatedHashDigest = self::_hashCalculator($hmHashMethod, $szPreSharedKey, $szStringBeforeHash);
			if(strtoupper($formVariables['HashDigest']) == strtoupper($szCalculatedHashDigest))
			{
				$boMatch = true;
			}
			
			return $boMatch;
		}
		
		/**
		 * Hash validator mechanism for the 3D Secure Authentication required hash in the transparent redirect payment mode
		 *
		 * @param unknown_type $formVariables
		 * @param unknown_type $szPassword
		 * @param unknown_type $hmHashMethod
		 * @param unknown_type $szPreSharedKey
		 * @return unknown
		 */
		public static function compareThreeDSecureAuthenticationRequiredHashDigest($formVariables, $szPassword, $hmHashMethod, $szPreSharedKey)
		{
			$boMatch = false;
			$szCalculatedHashDigest;
			$szStringBeforeHash;
			
			$szStringBeforeHash = 'MerchantID='.$formVariables['MerchantID'].'&'.
									'Password='.$szPassword.'&'.
									'StatusCode='.$formVariables['StatusCode'].'&'.
									'Message='.$formVariables['Message'].'&'.
									'CrossReference='.$formVariables['CrossReference'].'&'.
									'OrderID='.$formVariables['OrderID'].'&'.
									'TransactionDateTime='.$formVariables['TransactionDateTime'].'&'.
									'ACSURL='.$formVariables['ACSURL'].'&'.
									'PaREQ='.$formVariables['PaREQ'];
			
			if ($hmHashMethod == Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_MD5 ||
				$hmHashMethod == Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_SHA1)
			{
				$szStringBeforeHash = 'PreSharedKey='.$szPreSharedKey.'&'.$szStringBeforeHash;
			}
			
			$szCalculatedHashDigest = self::_hashCalculator($hmHashMethod, $szPreSharedKey, $szStringBeforeHash);
			if(strtoupper($formVariables['HashDigest']) == strtoupper($szCalculatedHashDigest))
			{
				$boMatch = true;
			}
			
			return $boMatch;
		}
		
		/**
		 * Convert a URL string to a name value array collection  
		 *
		 * @param unknown_type $szInputVariableString
		 * @return unknown
		 */
		public static function getVariableCollectionFromString($szInputVariableString)
		{
			$arVariableCollection = array();
			
			$arURLVariableArray = explode('&', $szInputVariableString);
			for($nCount = 0; $nCount < sizeof($arURLVariableArray); $nCount++)
			{
				$szNameValue = $arURLVariableArray[$nCount];
				$arNameValue = explode('=', $szNameValue);
				if(sizeof($arNameValue) == 1)
				{
					$arVariableCollection[$arNameValue[0]] = '';
				}
				else
				{
					$arVariableCollection[$arNameValue[0]] = $arNameValue[1];
				}
			}
			
			return ($arVariableCollection);
		}
		
		/**
		 * Hash validator mechanism for the SERVER and SERVER_PULL methods
		 *
		 * @param unknown_type $formVariables
		 * @param unknown_type $szPassword
		 * @param unknown_type $hmHashMethod
		 * @param unknown_type $szPreSharedKey
		 */
		public static function compareServerHashDigest($formVariables, $szPassword, $hmHashMethod, $szPreSharedKey)
		{
			$boMatch = false;
			$szHashDigest = isset($formVariables['HashDigest']) ? $formVariables['HashDigest'] : false;
			$szMerchantID = isset($formVariables['MerchantID']) ? $formVariables['MerchantID'] : false;
			$szCrossReference = isset($formVariables['CrossReference']) ? $formVariables['CrossReference'] : false;
			$szOrderID = isset($formVariables['OrderID']) ? $formVariables['OrderID'] : false;
			
			$szStringBeforeHash = 'MerchantID='.$szMerchantID.'&'.
									'Password='.$szPassword.'&'.
									'CrossReference='.$szCrossReference.'&'.
									'OrderID='.$szOrderID;
			
			if ($hmHashMethod == Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_MD5 ||
				$hmHashMethod == Cardsave_Cardsaveonlinepayments_Model_Source_HashMethod::HASH_METHOD_SHA1)
			{
				$szStringBeforeHash = 'PreSharedKey='.$szPreSharedKey.'&'.$szStringBeforeHash;
			}
			
			$szCalculatedHashDigest = self::_hashCalculator($hmHashMethod, $szPreSharedKey, $szStringBeforeHash);
			
			if(strtoupper($szHashDigest) === strtoupper($szCalculatedHashDigest))
			{
				$boMatch = true;
			}
			
			return $boMatch;
		}
		
		// TODO : REMOVE
		/**
		 * Transform the string Magento version number into an integer ready for comparison
		 *
		 * @param unknown_type $magentoVersion
		 * @return unknown
		 */
		/*public static function getVersion($magentoVersion)
		{
			//$nVersion = Mage::getVersion();
	    	$pattern = '/[^\d]/';
			$magentoVersion = preg_replace($pattern, '', $magentoVersion);
			
			while(strlen($magentoVersion) < 4)
			{
				$magentoVersion .= '0';
			}
			$magentoVersion = (int)$magentoVersion;
			
			return $magentoVersion;
		}*/
    }
?>