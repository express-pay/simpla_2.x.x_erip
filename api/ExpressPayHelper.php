<?php
require_once(dirname(__FILE__).'/ExpressPayLog.php');

class ExpressPayHelper
{
    public function compute_signature_add_invoice($token,$request_params, $secret_word) {
        $logs = new ExpressPayLog();

        $secret_word = trim($secret_word);
        $logs->log_info('compute_signature_add_invoice','getting a secret word; secret_word - '.$secret_word);
        $normalized_params = array_change_key_case($request_params, CASE_LOWER);

        $logs->log_info('compute_signature_add_invoice','getting a request_params; request_params - '.implode(' , ',$request_params));
        $api_method = array(
            "token",
            "accountno",
            "amount",
            "currency",
            "expiration",
            "info",
            "surname",
            "firstname",
            "patronymic",
            "city",
            "street",
            "house",
            "building",
            "apartment",
            "isnameeditable",
            "isaddresseditable",
            "isamounteditable"     
        );

        $result = $token;
        $logs->log_info('compute_signature_add_invoice','getting a result before; result - '.$result);

        foreach ($api_method as $item)
            $result .= $normalized_params[$item];
        
        $logs->log_info('compute_signature_add_invoice','getting a result after; result - '.$result);
        
        $hash = strtoupper(hash_hmac('sha1', $result, $secretWord));

        $logs->log_info('compute_signature_add_invoice','getting a hash; hash - '.$hash);
        return $hash;
    }
    
    public function computeSignature($signatureParams, $secretWord, $method) {
        $logs = new ExpressPayLog();
        $logs->log_info('compute_signature','getting a secret word; secret_word - '.($secretWord == '' ? 'is empty' : $secretWord));

        $normalizedParams = array_change_key_case($signatureParams, CASE_LOWER);

        $logs->log_info('compute_signature','getting a normalizedParams; normalizedParams - '.implode(' , ',$normalizedParams));
        $mapping = array(
            "add-invoice" => array(
                                    "token",
                                    "accountno",
                                    "amount",
                                    "currency",
                                    "expiration",
                                    "info",
                                    "surname",
                                    "firstname",
                                    "patronymic",
                                    "city",
                                    "street",
                                    "house",
                                    "building",
                                    "apartment",
                                    "isnameeditable",
                                    "isaddresseditable",
                                    "isamounteditable",
                                    "emailnotification"),
            "get-details-invoice" => array(
                                    "token",
                                    "id"),
            "cancel-invoice" => array(
                                    "token",
                                    "id"),
            "status-invoice" => array(
                                    "token",
                                    "id"),
            "get-list-invoices" => array(
                                    "token",
                                    "from",
                                    "to",
                                    "accountno",
                                    "status"),
            "get-list-payments" => array(
                                    "token",
                                    "from",
                                    "to",
                                    "accountno"),
            "get-details-payment" => array(
                                    "token",
                                    "id"),
            "add-card-invoice"  =>  array(
                                    "token",
                                    "accountno",                 
                                    "expiration",             
                                    "amount",                  
                                    "currency",
                                    "info",      
                                    "returnurl",
                                    "failurl",
                                    "language",
                                    "sessiontimeoutsecs",
                                    "expirationdate"),
           "card-invoice-form"  =>  array(
                                    "token",
                                    "cardinvoiceno"),
            "status-card-invoice" => array(
                                    "token",
                                    "cardinvoiceno",
                                    "language"),
            "reverse-card-invoice" => array(
                                    "token",
                                    "cardinvoiceno")
        );
        $apiMethod = $mapping[$method];
        $result = "";

        foreach ($apiMethod as $item){
            $result .= $normalizedParams[$item];
        }

        $logs->log_info('compute_signature','getting a result after; result - '.$result);

        $hash = strtoupper(hash_hmac('sha1', $result, $secretWord, false));
        $logs->log_info('compute_signature','getting a hash; hash - '.$hash);
        return $hash;
    }

    public function compute_signature($json, $secret_word) {
        $hash = NULL;
        $secret_word = trim($secret_word);
        
        if(empty($secret_word))
            $hash = strtoupper(hash_hmac('sha1', $json, ""));
        else
            $hash = strtoupper(hash_hmac('sha1', (string)$json, $secret_word));

        return $hash;
    }	
}