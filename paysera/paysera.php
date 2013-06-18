<?php

defined('_JEXEC') or die('Restricted access');

require_once('vendor/webtopay/libwebtopay/WebToPay.php');

class plgHikashoppaymentPaysera extends JPlugin {
    function onPaymentDisplay(&$order, &$methods, &$usable_methods) {
        if (!empty($methods)) {
            foreach ($methods as $method) {
                if ($method->payment_type != 'paysera' || !$method->enabled) {
                    continue;
                }
                if (!empty($method->payment_zone_namekey)) {
                    $zoneClass = hikashop::get('class.zone');
                    $zones     = $zoneClass->getOrderZones($order);
                    if (!in_array($method->payment_zone_namekey, $zones)) {
                        return true;
                    }
                }

                $usable_methods[$method->ordering] = $method;
            }
        }

        return true;
    }

    function onPaymentSave(&$cart, &$rates, &$payment_id) {
        $usable = array();
        $this->onPaymentDisplay($cart, $rates, $usable);
        $payment_id = (int)$payment_id;
        foreach ($usable as $usable_method) {
            if ($usable_method->payment_id == $payment_id) {
                return $usable_method;
            }
        }
        return false;
    }

    function onPaymentConfiguration(&$element) {
        $this->paysera = JRequest::getCmd('name', 'paysera');
        if (empty($element)) {
            $element                               = null;
            $element->payment_name                 = 'Paysera';
            $element->payment_description          = 'Pay via Paysera.com';
            $element->payment_images               = ''; //Collect_on_delivery
            $element->payment_type                 = $this->paysera;
            $element->payment_params               = null;
            $element->payment_params->order_status = 'created';
            $element                               = array($element);
        }
        $bar = & JToolBar::getInstance('toolbar');
        JToolBarHelper::save();
        JToolBarHelper::apply();
        JToolBarHelper::cancel();
        JToolBarHelper::divider();
        $bar->appendButton('Pophelp', 'payment-paysera-form');
        hikashop::setTitle(JText::_('PAYSERA'), 'plugin', 'plugins&plugin_type=payment&task=edit&name=' . $this->paysera);
        $app =& JFactory::getApplication();
        $app->setUserState(HIKASHOP_COMPONENT . '.payment_plugin_type', $this->paysera);
        $this->category       = hikashop::get('type.categorysub');
        $this->category->type = 'status';
    }

    function onPaymentConfigurationSave(&$element) {
        return true;
    }

    function onAfterOrderConfirm(&$order, &$methods, $method_id) {
        $method                 =& $methods[$method_id];
        $orderObj               = new stdClass();
        $orderObj->order_status = $method->payment_params->order_status;
        $orderObj->order_id     = $order->order_id;
        $orderClass             = hikashop::get('class.order');
        $orderClass->save($orderObj);
        $app  =& JFactory::getApplication();
        $name = $method->payment_type . '_end.php';

        $null          = null;
        $currencyClass = hikashop::get('class.currency');

        $amount    = number_format(($order->order_full_price * 100), 0, '', '');
        $curencies = $currencyClass->getCurrencies($order->order_currency_id, $null);
        $currency  = reset($curencies);
        $Language  = & JFactory::getLanguage();
        $Address   = $order->cart->shipping_address;

        $acceptURL   = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id=' . $order->order_id;
        $cancelURL   = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id=' . $order->order_id;
        $callbackURL = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=paysera&tmpl=component&lang=en';

        try {
            $request = WebToPay::buildRequest(array(
                'projectid'     => $method->payment_params->project_id,
                'sign_password' => $method->payment_params->project_pass,

                'orderid'       => hikashop::encode($order),
                'amount'        => $amount,
                'currency'      => $currency->currency_code,
                'lang'          => substr($Language->get('name'), 0, 3),

                'accepturl'     => $acceptURL,
                'cancelurl'     => $cancelURL,
                'callbackurl'   => $callbackURL,
                'payment'       => '',
                'country'       => 'LT',

                'logo'          => '',
                'p_firstname'   => $Address->address_firstname,
                'p_lastname'    => $Address->address_lastname,
                'p_email'       => $order->customer->user_email,
                'p_street'      => $Address->address_street,
                'p_city'        => $Address->address_city,
                'p_zip'         => $Address->address_post_code,
                'test'          => $method->payment_params->test_mode,
            ));
        } catch (WebToPayException $e) {
            echo get_class($e) . ': ' . $e->getMessage();
        }

        JHTML::_('behavior.mootools');
        $app  =& JFactory::getApplication();
        $name = $method->payment_type . '_end.php';
        $path = JPATH_THEMES . DS . $app->getTemplate() . DS . 'hikashoppayment' . DS . $name;
        if (!file_exists($path)) {
            if (version_compare(JVERSION, '1.6', '<')) {
                $path = JPATH_PLUGINS . DS . 'hikashoppayment' . DS . $name;
            } else {
                $path = JPATH_PLUGINS . DS . 'hikashoppayment' . DS . $method->payment_type . DS . $name;
            }
            if (!file_exists($path)) {
                return true;
            }
        }

        require($path);
        return true;
    }

    function onPaymentNotification(&$statuses) {
        $currencyClass = hikashop::get('class.currency');
        $pluginsClass  = hikashop::get('class.plugins');
        $elements      = $pluginsClass->getMethods('payment', 'paysera');
        $element       = reset($elements);
        $null          = null;

        try {
            $response = WebToPay::validateAndParseData($_GET, $element->payment_params->project_id, $element->payment_params->project_pass);

            $orderId = isset($response['orderid']) ? $response['orderid'] : null;

            if (empty($orderId)) {
                throw new Exception('Order with this ID not found');
            }

            $orderClass = hikashop::get('class.order');
            $dbOrder    = $orderClass->get(hikashop::decode($orderId));

            $curencies = $currencyClass->getCurrencies($dbOrder->order_currency_id, $null);
            $currency  = reset($curencies);

            if ($dbOrder->order_status == 'confirmed') {
                exit('ok');
            }

            if ($response['status'] == 1) {


                if ($response['amount'] != intval(number_format(($dbOrder->order_full_price * 100), 0, '', ''))) {
                    throw new Exception("Price doesn't match");
                }

                $curencies = $currencyClass->getCurrencies($dbOrder->order_currency_id, $null);
                $currency  = reset($curencies);

                if ($response['currency'] != $currency->currency_code) {
                    throw new Exception("Currency does't match expected: {$currency->currency_code}, got {$response['currency']}");
                }

                $dbOrder->order_status = 'confirmed';

                if ($orderClass->save($dbOrder)) {
                    exit('OK');
                } else {
                    throw new Exception("DB error: could not update order");
                }
            }
            exit('OK');
        } catch (Exception $e) {
            exit($e->getMessage());
        }

    }
}
