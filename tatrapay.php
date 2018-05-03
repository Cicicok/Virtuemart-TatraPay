<?php

defined ('_JEXEC') or die('Restricted access');

/**
 * @version: 1.0.0 27.04.2018
 * @package: Tatrapay Virtuemart plugin
 * @copyright: 2018 Richard Forro, All rights reserved.
 * @author: 2018 Richard Forro(riki49@gmail.com)
 * @license: http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @description: TatraPay (Tatra banka Slovakia) payment plugin for Virtuemart
 * 
 * This plugin is was inpired by stardard payment, skrill, tco, paypal and others Virtuemart payment plugins. 
 */

if (!class_exists ('vmPSPlugin')) {
	require(VMPATH_PLUGINLIBS . DS . 'vmpsplugin.php');
}

class plgVmPaymentTatrapay extends vmPSPlugin {

	function __construct (& $subject, $config) {
		parent::__construct ($subject, $config);
		$this->_loggable = TRUE;
		$this->_debug = TRUE;
		$this->tableFields = array_keys ($this->getTableSQLFields ());
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$varsToPush = $this->getVarsToPush ();
		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);
  }
  
	/**
	 * Create the table for this plugin if it does not yet exist.
	 */    
  public function getVmPluginCreateTableSQL () {
    return $this->createTableSQL ('Payment Tatrapay Table');
  }

	/**
	 * Fields to create the payment table
	 */
	function getTableSQLFields () {
		$SQLfields = array(
			'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id' => 'int(1) UNSIGNED DEFAULT NULL',
			'order_number' => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name' => 'varchar(5000)',
			'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
			'payment_currency' => 'char(3)',
			'cost_per_transaction' => 'decimal(10,2)',
			'cost_percent_total' => 'decimal(10,2)',
			'tax_id' => 'smallint(1)',
      'user_session' => 'varchar(255)',
      'tpay_res' => 'varchar(255) DEFAULT NULL',
      'tpay_tid' => 'varchar(255) DEFAULT NULL'
		);

    return $SQLfields;
	}
  /**
   * Started after user presses confirm order
   */
	function plgVmConfirmedOrder ($cart, $order) {
		if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL;
		} // Another method was selected, do nothing

		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}
    
    $session = JFactory::getSession ();
		$return_context = $session->getId ();
    $this->logInfo ('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');
    
    if (!class_exists ('VirtueMartModelOrders')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}
		if (!class_exists ('VirtueMartModelCurrency')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'currency.php');
		}
    if (!class_exists ('TableVendors')) {
			require(VMPATH_ADMIN . DS . 'tables' . DS . 'vendors.php');
		}
		$vendorModel = VmModel::getModel ('Vendor');
		$vendorModel->setId (1);
		$vendor = $vendorModel->getVendor ();
		$vendorModel->addImages ($vendor, 1); //ToDo why picture
    
    $this->logInfo ('vendor: ' . $vendor, 'message');
    
		$this->getPaymentCurrency($method);	
		$currency_code_3 = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');
    
		// ToDo connected with backend settings, remove, check or change currency method 
    $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total,$method->payment_currency);
    if ($totalInPaymentCurrency['value'] <= 0) {
			vmInfo (vmText::_ ('VMPAYMENT_TATRAPAY_PAYMENT_AMOUNT_INCORRECT'));
			return FALSE;
		}
    
    if (empty($method->mid) || empty($method->key)) {
      JFactory::getApplication()->enqueueMessage(JText::_('VMPAYMENT_TATRAPAY_MID_OR_KEY_EMPTY'), 'Error');
      return FALSE;
	    }
    
    // prepare and store values into DB
    $dbValues['user_session'] = $return_context;
	  $dbValues['payment_name'] = $this->renderPluginName ($method);
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
		$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['payment_currency'] = $currency_code_3;
		$dbValues['payment_order_total'] = $totalInPaymentCurrency['value'];
		$dbValues['tax_id'] = $method->tax_id;
		$this->storePSPluginInternalData ($dbValues);
		
    // prepare data for request
    $request_data['MID'] = $method->mid;
    $request_data['AMT'] = number_format($totalInPaymentCurrency['value'],2,".",".");
    $request_data['CURR'] = $method->currency;
    $request_data['VS'] = $order['details']['BT']->virtuemart_order_id;
    $request_data['RURL'] = JROUTE::_(JURI::root() .
                            'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=' .
                            $order['details']['BT']->virtuemart_paymentmethod_id) .
                            '&on=' .
                            $order['details']['BT']->order_number;
    $request_data['REM'] = empty($method->rem) ? JFactory::getApplication()->getCfg('mailfrom') : $method->rem;
    $request_data['TIMESTAMP'] = gmdate("dmYHis"); //DDMMYYYYHHMISS 
    $request_data['AREDIR'] = "1";
    $request_data['LANG'] = "sk";
    // calculate HMAC
    $stringToSign = $request_data['MID'] . $request_data['AMT'] . $request_data['CURR'] . $request_data['VS'] .
                    $request_data['RURL'] . $request_data['REM'] . $request_data['TIMESTAMP'];
    $request_data['HMAC'] = hash_hmac("sha256", $stringToSign, pack("H*", $method->key));
    
    //create POST form
    $form = '<form action="https://moja.tatrabanka.sk/cgi-bin/e-commerce/start/tatrapay" method="POST">';
    foreach ($request_data as $key => $val) {
      $form.= '<input type="hidden" name="'.$key.'" value="'.$val.'" />';  
    }
    $form.= '<span class="addtocart-button"><input name="submit" class="addtocart-button" value="'.JText::_('VMPAYMENT_TATRAPAY_SUBMIT_BUTTON').'" type="submit"></span>';
    $form.= '</form>';
        
    $html = $this->renderByLayout('redirect_to_bank', array(
			'confirm_button' =>$form
		));
    $modelOrder = VmModel::getModel ('orders');
		$order['order_status'] = $method->status_pending;
		$order['customer_notified'] = 0;
		$order['comments'] = '';
		$modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);		
		
    vRequest::setVar ('html', $html);
		
    return TRUE;
  }
	
  /**
   * Returns icon and text according to TatraPay response
   */
  function _getTpayResImg ($res) {
    switch($res) {
      case 'OK':
        $img = 'tick_mark_icon.png';  
        break;
      case 'FAIL':
        $img = 'x_icon.png';  
        break;
      case 'TOUT':
        $img = 'question_icon.png';  
        break;
      default:
        return $res;
      }      
    
    return '<img src="' . JURI::root() . '/plugins/vmpayment/tatrapay/tatrapay/assets/images/' . $img . '"> ' . $res;
  }
    
  /**
   * Display stored payment data for an order (in orders view backend)
   */
  function plgVmOnShowOrderBEPayment ($virtuemart_order_id, $virtuemart_payment_id) {
		if (!$this->selectedThisByMethodId ($virtuemart_payment_id)) {
			return NULL; 
		}
		if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
			return NULL;
		}

    $db = JFactory::getDBO ();
    $db->setQuery("SELECT `tpay_res`, `tpay_tid` FROM #__virtuemart_payment_plg_tatrapay WHERE virtuemart_order_id='".$virtuemart_order_id."'");
		$databaseFields = $db->loadAssoc();
    
		VmConfig::loadJLang('com_virtuemart');
		$html = '<table class="adminlist table">' . "\n";                                   
		$html .= $this->getHtmlHeaderBE ();
		$html .= $this->getHtmlRowBE ('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
    $html .= $this->getHtmlRowBE ('VMPAYMENT_TATRAPAY_SHOW_ORDER_RES', $this->_getTpayResImg($databaseFields['tpay_res']));
    $html .= $this->getHtmlRowBE ('VMPAYMENT_TATRAPAY_SHOW_ORDER_TID', $databaseFields['tpay_tid']);
		$html .= '</table>' . "\n";
		
    return $html;
	}
  
  /**
	 * Check if the payment conditions are fulfilled for this payment method
	 */
	protected function checkConditions ($cart, $method, $cart_prices) {
		$this->convert_condition_amount($method);
		$amount = $this->getCartAmount($cart_prices);
		$address = $cart -> getST();

		//vmdebug('standard checkConditions',  $amount, $cart_prices['salesPrice'],  $cart_prices['salesPriceCoupon']);
		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
			($method->min_amount <= $amount AND ($method->max_amount == 0)));
		if (!$amount_cond) {
			return FALSE;
		}
		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array ($method->countries)) {
				$countries[0] = $method->countries;
			} else {
				$countries = $method->countries;
			}
		}

		// probably did not gave his BT:ST address
		if (!is_array ($address)) {
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id'])) {
			$address['virtuemart_country_id'] = 0;
		}
		if (count ($countries) == 0 || in_array ($address['virtuemart_country_id'], $countries) ) {
			return TRUE;
		}

		return FALSE;
	}
  
  /**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 */
	function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id) {
		return $this->onStoreInstallPluginTable ($jplugin_id);
	}
  
  /**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 */
	public function plgVmOnSelectCheckPayment (VirtueMartCart $cart, &$msg) {
    return $this->OnSelectCheck ($cart);
	}
  
  /**
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for example
	 */
	public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {
		return $this->displayListFE ($cart, $selected, $htmlIn);
	}

  public function plgVmonSelectedCalculatePricePayment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
		return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
	}
  
  /**
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 */
	function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {
		return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
	}
  
  /**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 */
	public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
    $this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 */
	function plgVmonShowOrderPrintPayment ($order_number, $method_id) {
		return $this->onShowOrderPrint ($order_number, $method_id);
	}
  
  function plgVmDeclarePluginParamsPaymentVM3( &$data) {
		return $this->declarePluginParams('payment', $data);
	}
	
  function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table) {
    return $this->setOnTablePluginParams ($name, $id, $table);
	}

  /**
   * Stored data in DB
   * ToDo replace for virtuemart DB update method
   */
  function _storeResponseData ($method, $tpay_data, $virtuemart_order_id) {
  
    $db = JFactory::getDBO ();
    $db->setQuery("SELECT * FROM #__virtuemart_payment_plg_tatrapay WHERE virtuemart_order_id='".$virtuemart_order_id."'");
		$databaseFields = $db->loadAssoc();
    $databaseFields['tpay_res'] = $tpay_data['RES'];
    $databaseFields['tpay_tid'] = $tpay_data['TID']; 

    $this->storePSPluginInternalData ($databaseFields, 'virtuemart_order_id', true);
  }
  
	/**
	 * Returns ECDSA public key from Tatra banka based on provided Id
	 */
  function _getPublicKeyById($ecdsa_key_id) {
    $keys_file = file_get_contents("https://moja.tatrabanka.sk/e-commerce/ecdsa_keys.txt");
    
    if(preg_match("~KEY_ID: $ecdsa_key_id\s+STATUS: VALID\s+(-{5}BEGIN PUBLIC KEY-{5}.+?-{5}END PUBLIC KEY-{5})~s", $keys_file, $match)){
      return $match[1];
    }
    else {
      $this->logInfo ('Error in getting key from Tatra banka.', 'error');    
    }
  }  
  
  /**
	 * This event is fired when the  method returns to the shop after the transaction
	 *
	 *  the method itself should send in the URL the parameters needed
	 *  NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 */
  function plgVmOnPaymentResponseReceived(&$html) {
    if (!class_exists ('VirtueMartModelOrders')) {
		  require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}	
    
    $virtuemart_paymentmethod_id = vRequest::getInt('pm', 0);
    $order_number = vRequest::getVar('on', 0);

    if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
      return NULL; // Another method was selected, do nothing
    }
    
    if (!$this->selectedThisElement($method->payment_element)) {
      return NULL;
    }
    
    // get order ID from URL's order_number
    if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number))) {
		  return NULL; // return Error not NULL
		}
    
    // request URL data
    $tpay_data = vRequest::getGet();
    // check if tatrapay returned same order_id as it should
    if ($tpay_data['VS'] != $virtuemart_order_id) {
      return NULL; // return Error not NULL
    }
    
    // store response data
    $this->_storeResponseData($method, $tpay_data, $virtuemart_order_id);
    
    // calculate HMAC
    $hmac_string = $tpay_data['AMT'] . $tpay_data['CURR'] . $tpay_data['VS'] . $tpay_data['RES'] .
                    $tpay_data['TID'] . $tpay_data['TIMESTAMP']; 
    $calculated_hmac = hash_hmac("sha256", $hmac_string, pack("H*", $method->key));
    
    // calculate ECDSA
    $signiture_string = $hmac_string . $tpay_data['HMAC'];
    $publicKey = $this->_getPublicKeyById($tpay_data['ECDSA_KEY']);
    $verified_ecdsa = openssl_verify($signiture_string, pack("H*", $tpay_data['ECDSA']), $publicKey, "sha256");

    $modelOrder = VmModel::getModel('orders');
    $order = $modelOrder->getOrder($virtuemart_order_id);
      
    if ($calculated_hmac === $tpay_data['HMAC'] && $verified_ecdsa === 1) {
      switch ($tpay_data['RES']) {
        case "OK":
          $order['order_status'] = $method->status_success;
		      $order['customer_notified'] = 1;
          $order['comments'] = vmText::sprintf('VMPAYMENT_TATRAPAY_ORDER_STATUS_SUCCESS_COMMENT', $order_number);
          $modelOrder->updateStatusForOneOrder ($virtuemart_order_id, $order, TRUE);
          
          $html = $this->renderByLayout('post_payment', array(
            'confirm_icon' => "plugins/vmpayment/tatrapay/tatrapay/assets/images/tick_mark_icon.png",
            'confirm_title' => vmText::_ ('VMPAYMENT_TATRAPAY_POST_PAYMENT_SUCCESS_TITLE'),
            'order_number' =>$order['details']['BT']->order_number,
			      'order_pass' =>$order['details']['BT']->order_pass
		        ));
          
          // delete cart
          if (!class_exists('VirtueMartCart')) require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
	        $cart = VirtueMartCart::getCart();
		      $cart->emptyCart (); 
                    
          vRequest::setVar ('html', $html);
          break;
          
        case "FAIL":
          $order['order_status'] = $method->status_failed;
		      $order['customer_notified'] = 0; //don't need to notify per email
          $order['comments'] = vmText::sprintf('VMPAYMENT_TATRAPAY_ORDER_STATUS_FAILED_COMMENT');
          $modelOrder->updateStatusForOneOrder ($virtuemart_order_id, $order, TRUE);
          
          JFactory::getApplication()->enqueueMessage(JText::_('VMPAYMENT_TATRAPAY_ORDER_STATUS_FAILED_MSG'), 'error');	
		      $app = JFactory::getApplication ();
          $app->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart', false));
          echo 'went back';
          break;
          
        case "TOUT":
          $order['order_status'] = $method->status_tout;
		      $order['customer_notified'] = 1;
          $order['comments'] = vmText::sprintf('VMPAYMENT_TATRAPAY_ORDER_STATUS_TOUT_COMMENT', $order_number);
          $modelOrder->updateStatusForOneOrder ($virtuemart_order_id, $order, TRUE);
          
          $html = $this->renderByLayout('post_payment', array(
            'confirm_icon' => "plugins/vmpayment/tatrapay/tatrapay/assets/images/question_icon.png",
            'confirm_title' => vmText::_ ('VMPAYMENT_TATRAPAY_POST_PAYMENT_TOUT_TITLE'),
            'confirm_detail' => vmText::_ ('VMPAYMENT_TATRAPAY_POST_PAYMENT_TOUT_COMMENT'), 
            'order_number' =>$order['details']['BT']->order_number,
			      'order_pass' =>$order['details']['BT']->order_pass
		        ));
          
          // delete cart
          if (!class_exists('VirtueMartCart')) require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
	        $cart = VirtueMartCart::getCart();
		      $cart->emptyCart (); 
                    
          vRequest::setVar ('html', $html);
          break;
          
        default:
          $this->logInfo ('Unknown response code returned from Tatra banka in order: ' . $order_number, 'error');
          
          $html = $this->renderByLayout('post_payment', array(
            'confirm_icon' => "plugins/vmpayment/tatrapay/tatrapay/assets/images/x_icon.png",
            'confirm_title' => vmText::_ ('VMPAYMENT_TATRAPAY_POST_PAYMENT_FATAL_ERROR_TITLE'),
            'confirm_detail' => vmText::_ ('VMPAYMENT_TATRAPAY_POST_PAYMENT_FATAL_ERROR_COMMENT'), 
            'order_number' =>$order['details']['BT']->order_number,
			      'order_pass' =>$order['details']['BT']->order_pass
		      ));
          vRequest::setVar ('html', $html);
      }
    }
    else {
      $this->logInfo ('HMAC or ECDSA verification error in order: ' . $order_number, 'error');
      
      $html = $this->renderByLayout('post_payment', array(
            'confirm_icon' => "plugins/vmpayment/tatrapay/tatrapay/assets/images/x_icon.png",
            'confirm_title' => vmText::_ ('VMPAYMENT_TATRAPAY_POST_PAYMENT_FATAL_ERROR_TITLE'),
            'confirm_detail' => vmText::_ ('VMPAYMENT_TATRAPAY_POST_PAYMENT_FATAL_ERROR_COMMENT'), 
            'order_number' =>$order['details']['BT']->order_number,
			      'order_pass' =>$order['details']['BT']->order_pass
		      ));
      vRequest::setVar ('html', $html);
    }
      
    return TRUE;
	}
   
}		
// No closing tag
