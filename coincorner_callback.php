<?php
require 'includes/cc-lib.php';
require_once 'includes/application_top.php';

// After a successful checkout
if (isset($_GET['order'])) {
    $_SESSION['cart']->reset(true);
    zen_redirect('http://' . $_SERVER['SERVER_NAME'] .'/index.php?main_page=checkout_success');
}

// CoinCorner webhook
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    die();
}

$input = json_decode(file_get_contents('php://input'), true);

$resp = do_curl_request('https://checkout.coincorner.com/api/GetOrder', array( 
    'OrderId' => $input['OrderId']
));

// The poor man's enum!
final class order_status {
    const pending              = -99;
    const refunded             =  -5;
    const pending_refund       =  -4;
    const expired              =  -2;
    const cancelled            =  -1;
    const awaiting_payment     =   0;
    const pending_confirmation =   1;
    const complete             =   2;
}

switch ($resp['OrderStatus']) {
    case order_status::cancelled:
        $order_status = MODULE_PAYMENT_COINCORNER_ORDER_CANCELLED;
        break;

    case order_status::complete:
        $order_status = MODULE_PAYMENT_COINCORNER_ORDER_COMPLETE;
        break;

    case order_status::refunded: 
        $order_status = MODULE_PAYMENT_COINCORNER_ORDER_REFUNDED; 
        break;
        
    case order_status::expired:
        $order_status = MODULE_PAYMENT_COINCORNER_ORDER_EXPIRED;
        break;
    
    // Pending is the default state set by zencart
    default:
        $order_status = MODULE_PAYMENT_COINCORNER_ORDER_PENDING;
        break;
}

global $db;
$db->Execute("update ". TABLE_ORDERS . " set orders_status = " . $order_status . " where orders_id = ". intval($input['OrderId']));
http_response_code(200);
?>