<?php
require_once 'includes/application_top.php';

// SHA256 hash of nonce, account ID and API key, and salted with API secret.
// Has to be sent in lowercase per CoinCorner docs.
function make_signature($nonce) {
    // return strtolower(hash_hmac('sha256', $nonce . $account_id . $api_key, $api_secret));
    return strtolower(hash_hmac(
        'sha256', 
        $nonce . MODULE_PAYMENT_COINCORNER_ACCOUNT_ID . MODULE_PAYMENT_COINCORNER_API_KEY, 
        MODULE_PAYMENT_COINCORNER_API_SECRET
    ));
}

function do_curl_request($url, $params) {
    $curl = curl_init();

    $nonce = (int)(microtime(true) * 1e6);
    $default_params = array(
        'APIKey' => MODULE_PAYMENT_COINCORNER_API_KEY,
        'Signature' => make_signature($nonce),
        'nonce' => $nonce,
    );

    $curl_options = array(
        CURLOPT_RETURNTRANSFER => 1, 
        CURLOPT_URL => $url,
    );

    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array());

    $headers[] = 'Content-Type: application/x-www-form-urlencoded';

    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query(array_merge($default_params, $params)));

    curl_setopt_array($curl, $curl_options);

    $response = json_decode(curl_exec($curl), true);
    curl_close($curl);
    return $response;
}
?>