<?php
class coincorner
{
    function __construct() {
        $this->code = 'coincorner';
        $this->title = MODULE_PAYMENT_COINCORNER_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_COINCORNER_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_COINCORNER_SORT_ORDER;
        $this->version = MODULE_PAYMENT_COINCORNER_EXTENSION_VERSION;

        // This decides if we show the payment option on the checkout screen.
        $this->enabled = MODULE_PAYMENT_COINCORNER_STATUS == 'True' && MODULE_PAYMENT_COINCORNER_API_KEY && MODULE_PAYMENT_COINCORNER_API_SECRET && MODULE_PAYMENT_COINCORNER_ACCOUNT_ID;
    }

    function install() {
        global $db, $messageStack;
        
        if (defined('MODULE_PAYMENT_COINCORNER_STATUS')) {
            $messageStack->add_session('The Coincorner module is already installed.', 'error');
            return 'failed';
        }

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable CoinCorner Module', 'MODULE_PAYMENT_COINCORNER_STATUS', 'False', 'Accept Bitcoin payments with CoinCorner?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Coincorner API key', 'MODULE_PAYMENT_COINCORNER_API_KEY', '', 'Your CoinCorner API key', '6', '1', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('CoinCorner API Secret', 'MODULE_PAYMENT_COINCORNER_API_SECRET', '', 'Your CoinCorner API secret', '6', '1', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('CoinCorner Account ID', 'MODULE_PAYMENT_COINCORNER_ACCOUNT_ID', '', 'Your CoinCorner account ID', '6', '1', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Invoice Currency', 'MODULE_PAYMENT_COINCORNER_INVOICE_CURRENCY', 'GBP', 'Which currency do you want to display to your customers?', '6', '0', 'zen_cfg_select_option(array(\'GBP\', \'EUR\', \'USD\'),', now());");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Settle Currency', 'MODULE_PAYMENT_COINCORNER_SETTLE_CURRENCY', 'GBP', 'Which currency do you want to settle the payment in?', '6', '0', 'zen_cfg_select_option(array(\'GBP\', \'EUR\'),', now());");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Payment option display order.', 'MODULE_PAYMENT_COINCORNER_SORT_ORDER', '0', 'The sort order of payment options. Lowest is displayed first.', '6', '8', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Refunded Order Status', 'MODULE_PAYMENT_COINCORNER_ORDER_REFUNDED', '2', 'Set the status when an payment is refunded', '6', '6', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Cancelled Order Status', 'MODULE_PAYMENT_COINCORNER_ORDER_CANCELLED', '2', 'Set the status when an payment is cancelled', '6', '6', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Pending Order Status', 'MODULE_PAYMENT_COINCORNER_ORDER_PENDING', '2', 'Set the status when an payment is pending', '6', '6', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Complete Order Status', 'MODULE_PAYMENT_COINCORNER_ORDER_COMPLETE', '2', 'Set the status when an payment is complete', '6', '6', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Expired Order Status', 'MODULE_PAYMENT_COINCORNER_ORDER_EXPIRED', '2', 'Set the status when a payment has expired', '6', '6', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
    }

    function selection() { 
        return array('id' => $this->code, 'module' => $this->title);
    }
    
    function check() { 
        global $db;
        
        if (!isset($this->_check)) {
            $check_query  = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_COINCORNER_STATUS'");
            $this->_check = $check_query->RecordCount();
        }

        return $this->_check;
    }

    function after_process() {
        require 'includes/cc-lib.php';
        global $insert_id, $db, $order;

        $store_name = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key='STORE_NAME' limit 1");
        $products = $db->Execute("select oc.products_id, oc.products_quantity, pd.products_name from " . TABLE_ORDERS_PRODUCTS . " as oc left join " . TABLE_PRODUCTS_DESCRIPTION . " as pd on pd.products_id=oc.products_id  where orders_id=" . intval($insert_id));
        
        $description = array();
        while (!$products->EOF) {
            $description[] = $products->fields['products_quantity'] . ' × ' . $products->fields['products_name'];
            $products->MoveNext();
        }

        $serb = $_SERVER['HTTPS'] ? 'https://' : 'http://' . $_SERVER['SERVER_NAME'];

        $resp = do_curl_request('https://checkout.coincorner.com/api/CreateOrder', array(
            'OrderId' => $insert_id,
            'InvoiceAmount' => $order->info['total'],
            'SettleCurrency' => MODULE_PAYMENT_COINCORNER_SETTLE_CURRENCY,
            'InvoiceCurrency' => MODULE_PAYMENT_COINCORNER_INVOICE_CURRENCY,
            'NotificationURL' => $serb . '/coincorner_callback.php',
            'SuccessRedirectURL' => $serb . '/coincorner_callback.php?order=' . $insert_id,
            'FailRedirectURL' => $serb . '/index.php?main_page=shopping_cart',
            'ItemDescription' => join($description, ','),
            'WebsiteId' => $configuration->fields['configuration_value'] . ' order ' . $insert_id, 
        ));
        zen_redirect($resp);
    }

    function remove() {
        global $db;
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key LIKE 'MODULE\_PAYMENT\_COINCORNER\_%'");
    }

    // These are magic constants zencart uses to pass variables from the database to the plugin.
    function keys() {
        return array(
            'MODULE_PAYMENT_COINCORNER_INVOICE_CURRENCY',
            'MODULE_PAYMENT_COINCORNER_API_KEY',
            'MODULE_PAYMENT_COINCORNER_API_SECRET',
            'MODULE_PAYMENT_COINCORNER_ACCOUNT_ID',
            'MODULE_PAYMENT_COINCORNER_SETTLE_CURRENCY',
            'MODULE_PAYMENT_COINCORNER_STATUS',
            'MODULE_PAYMENT_COINCORNER_SORT_ORDER',
            'MODULE_PAYMENT_COINCORNER_ORDER_PENDING',
            'MODULE_PAYMENT_COINCORNER_ORDER_COMPLETE',
            'MODULE_PAYMENT_COINCORNER_ORDER_EXPIRED',
            'MODULE_PAYMENT_COINCORNER_ORDER_CANCELLED',
            'MODULE_PAYMENT_COINCORNER_ORDER_REFUNDED'
          );
    }

    // We don't need to do anything with these but the store won't load unless they exist.
    public function pre_confirmation_check() { return false; }
    public function confirmation() { return false; }
    public function process_button() { return false; }
    public function before_process() { return false; }
    public function javascript_validation() { return false; }
    public function get_error() { return false; }
}