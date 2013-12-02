<?php
defined('_JEXEC') or die('Restricted access');

class plgHikashoppaymentPaysera extends hikashopPaymentPlugin {

    	var $accepted_currencies = array('LTL', 'USD', 'EUR');
    	var $multiple = true;
	var $name = 'paysera';
	var $pluginConfig = array(
		'project_id' => array('Project ID', 'input'),
		'project_pass' => array('Project password', 'input'),
		'test_mode' => array('Enable test mode?', 'boolean','0'),
		'verified_status' => array('VERIFIED_STATUS', 'orderstatus')
    	);
	
	function getPaymentDefaultValues(&$element) {
		$element->payment_name                    = 'Paysera';
		$element->payment_description             = 'Pay via Paysera.com';
		$element->payment_images                  = '';
		$element->payment_params->verified_status = 'confirmed';
	}

	function onAfterOrderConfirm(&$order, &$methods, $method_id) {
		parent::onAfterOrderConfirm($order, $methods, $method_id);

		$lang = JFactory::getLanguage();
        
		$amount    = number_format(($order->order_full_price * 100), 0, '', '');
		if(!empty($order->cart->shipping_address))
			$address   = $order->cart->shipping_address;
		else
			$address   = $order->cart->billing_address;

		$acceptURL   = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id=' . $order->order_id . $this->url_itemid;
		$cancelURL   = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id=' . $order->order_id . $this->url_itemid;
		$callbackURL = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=paysera&notif_id='.$method_id.'&order_id='.$order->order_id.'&tmpl=component&lang='.$this->locale;

		$request = null;
		require_once('vendor/webtopay/libwebtopay/WebToPay.php');
        
		try {
			$request = WebToPay::buildRequest(array(
				'projectid'     => $this->payment_params->project_id,
				'sign_password' => $this->payment_params->project_pass,
				
				'orderid'       => hikashop_encode($order),
				'amount'        => $amount,
				'currency'      => $this->currency->currency_code,
				'lang'          => substr($lang->get('name'), 0, 3),
				
				'accepturl'     => $acceptURL,
				'cancelurl'     => $cancelURL,
				'callbackurl'   => $callbackURL,
				'payment'       => '',
				'country'       => 'LT',
				
				'logo'          => '',
				'p_firstname'   => @$address->address_firstname,
				'p_lastname'    => @$address->address_lastname,
				'p_email'       => $order->customer->user_email,
				'p_street'      => @$address->address_street,
				'p_city'        => @$address->address_city,
				'p_zip'         => @$address->address_post_code,
				'test'          => $this->payment_params->test_mode,
			));
		} catch (WebToPayException $e) {
			echo get_class($e) . ': ' . $e->getMessage();
		}
        
		$this->request = $request;
	
		return $this->showPage('end');
	}

    	function onPaymentNotification(&$statuses) {
		$method_id = JRequest::getInt('notif_id', 0);
		$this->pluginParams($method_id);
		$this->payment_params =& $this->plugin_params;
		if(empty($this->payment_params))
			return false;

		require_once('vendor/webtopay/libwebtopay/WebToPay.php');

		try {
			$response = WebToPay::validateAndParseData($_GET, $this->payment_params->project_id, $this->payment_params->project_pass);
			
			$orderId = isset($response['orderid']) ? $response['orderid'] : null;
			
			if (empty($orderId)) {
				throw new Exception('Order with this ID not found');
			}
			
			$order_id = (int)hikashop_decode($orderId);
			$dbOrder = $this->getOrder($order_id);
			if(empty($dbOrder) || $method_id != $dbOrder->order_payment_id)
				return false;
			$this->loadOrderData($dbOrder);
			
			if ($dbOrder->order_status == $this->payment_params->verified_status) {
				exit('ok');
			}
			
			if ($response['status'] == 1) {
				if ($response['amount'] != intval(number_format(($dbOrder->order_full_price * 100), 0, '', ''))) {
					throw new Exception("Price doesn't match");
				}
				
				if ($response['currency'] != $this->currency->currency_code) {
					throw new Exception("Currency does't match expected: {$currency->currency_code}, got {$response['currency']}");
				}
				
				$history = new stdClass();
				$history->notified = 1;
				
				$payment_status = 'Accepted';
				$order_status = $this->payment_params->verified_status;
				$order_text = '';
				
				$email = new stdClass();
				$email->body = str_replace('<br/>',"\r\n",JText::sprintf('PAYMENT_NOTIFICATION_STATUS','Paysera', $payment_status)).' '.JText::sprintf('ORDER_STATUS_CHANGED', $statuses[$order_status])."\r\n\r\n".$order_text;
				$email->subject = JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER', 'Paysera', $payment_status, $dbOrder->order_number);
				
				$this->modifyOrder($order_id, $order_status, $history, $email);
			}
			exit('OK');
		} catch (Exception $e) {
			exit($e->getMessage());
		}
	}
}
