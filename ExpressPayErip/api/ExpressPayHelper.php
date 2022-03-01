<?php
class ExpressPayHelper
{
    /**
     * Обработка уведомления от Express-pay.by
     */
    public static function Notification($logs)
    {
        // Получение данных в json формате
        $dataJSON = ( isset($_REQUEST['Data']) ) ? htmlspecialchars_decode($_REQUEST['Data']) : '';
        $dataJSON = stripcslashes($dataJSON);
        $logs->log_info('Notification','Received data from the Express-pay.by : Data - '.$dataJSON);
        
        // Преобразование из json в array
        $data = array();
        try {
            $data = json_decode($dataJSON,true); 
            $logs->log_info('Notification','Converting data from json to an array : Data - '.implode(',',$data));
        } 
        catch(Exception $e) {
            $logs->log_error('Notification', "Failed to decode data; RESPONSE - " . $dataJSON);
            header('HTTP/1.1 400 Bad Request');
            print $st = 'FAILED | failed to decode data';
            return;
        }

        // Получение подписи
        $signature = ( isset($_REQUEST['Signature']) ) ? $_REQUEST['Signature'] : ''; 
        $logs->log_info('Notification','Receipt of a signature : signature - '.($signature !== '' ? $signature : 'no signature'));

        if(isset($data['AccountNo'])){
            $simpla = new Simpla();

            // Получение заказа по его номеру
            $order = $simpla->orders->get_order(intval($data['AccountNo']));
            $logs->log_info('Notification','Receiving an order by AccountNo; AccountNo - '.$data['AccountNo'].'; Order - '.($order == null ? "is null" : $order->id));
            
            // Получение настрек для данного метода
            $settings = $simpla->payment->get_payment_settings($order->payment_method_id);
            $logs->log_info('Notification','Getting settings; Settings - '.json_encode($settings));
            
            if(isset($settings['is_use_signature_notify']) && $settings['is_use_signature_notify']) // Проверка на использование цифровой подписи
            {   
                // Проверка на совпадение подписей
                $valid_signature = self::computeSignature(array("data" => $dataJSON), $settings['secret_key_norify'], 'notification');
                if ($valid_signature == $signature)

                {
                    $logs->log_info('Notification','Signatures match');
                }
                else
                {
                    $logs->log_error('Notification', 'Signatures do not match; Received signature - '.$signature.'; Calculated signature - '.$valid_signature);
                    header('HTTP/1.1 403 FORBIDDEN');
                    print $st = 'FAILED | Access is denied'; //Ошибка в параметрах
                    return;
                }
            }
            else
            {
                $logs->log_info('Notification','Signature is not verified');
            }
            self::processNotification($logs,$data,$simpla);
            return;
        }
        $logs->log_error('Notification', "Fail to parse the server response; RESPONSE - " . $dataJSON);
        header('HTTP/1.1 400 Bad Request');
        print $st = 'FAILED | the notice is not processed';
    }

    private static function processNotification($logs, $data, $simpla){
        $logs->log_info('processNotification','processing of notifications from the server');
        
        $cmdtype    = $data['CmdType'];
        $status     = $data['Status'];
        $amount     = $data['Amount'];
    
        $logs->log_info('processNotification','Received POST response; CmdType - '.$cmdtype.'; Status - '.$status.'; AccountNo - '.$data['AccountNo']
        .'; Amount - '.$amount.'; Created - '.$data['Created'].'; Service - '.$data['Service'].'; Payer - '.$data['Payer'].'; Address - '.$data['Address']);
        
        $order = $simpla->orders->get_order(intval($data['AccountNo']));
        switch($cmdtype)
        {
            case 1: // Поступление нового платежа
                $simpla->orders->update_order(intval($order->id), array('note'=>$order->note .' '. "Ожидает оплату"));
                $logs->log_info('processNotification','status processing "Pending payment"');
                header("HTTP/1.1 200 OK");
                print $st= 'OK | the notice is processed';
                return;
            case 2: // Отмена платежа
                $simpla->orders->update_order(intval($order->id), array('note'=>$order->note .' '. "Отменен"));
                $simpla->orders->update_order(intval($order->id), array('paid'=>0));// Установим статус не оплачен
                $logs->log_info('processNotification','status processing "Canceled"');
                header("HTTP/1.1 200 OK");
                print $st= 'OK | the notice is processed';
                return;
            case 3: // Изменение статуса счета
                if(isset($status)){
                    switch($status){
                        case 1: // Ожидает оплату
	                        $simpla->orders->update_order(intval($order->id), array('note'=>$order->note .' '. "Ожидает оплату"));
                            $logs->log_info('processNotification','status processing "Pending payment"');
                            break;
                        case 2: // Просрочен
	                        $simpla->orders->update_order(intval($order->id), array('note'=>$order->note .' '. "Просрочен"));
                            $logs->log_info('processNotification','status processing "Expired"');
                            break;
                        case 3: // Оплачен
                            if($order->paid != 1)
                            {
                                $simpla->orders->update_order(intval($order->id), array('note'=>$order->note .' '. "Оплачен"));
                                $simpla->orders->update_order(intval($order->id), array('paid'=>1));// Установим статус оплачен
                                $simpla->orders->close(intval($order->id));// Спишем товары
                                //$simpla->notify->email_order_user(intval($order->id));
                                $simpla->notify->email_order_admin(intval($order->id));
                                $logs->log_info('processNotification','status processing "Paid"');
                            }
                            else
                            {
                                $logs->log_info('processNotification',"status don't change");
                                header("HTTP/1.1 200 OK");
                                print $st= 'OK | already paid';
                                return;
                            }
                            break;
                        case 4: // Оплачен частично 
	                        $simpla->orders->update_order(intval($order->id), array('note'=>$order->note .' '. "Оплачен частично"));
                            $logs->log_info('processNotification','status processing "Partially paid"');
                            break;
                        case 5: // Отменен
	                        $simpla->orders->update_order(intval($order->id), array('note'=>$order->note .' '. "Отменен"));
                            $simpla->orders->update_order(intval($order->id), array('paid'=>0));// Установим статус не оплачен
                            $logs->log_info('processNotification','status processing "Canceled"');
                            break;
                        case 6: // Оплачен с помощью банковской карты
                            if($order->paid != 1)
                            {
                                $simpla->orders->update_order(intval($order->id), array('note'=>$order->note .' '. "Оплачен"));
                                $simpla->orders->update_order(intval($order->id), array('paid'=>1));// Установим статус оплачен
                                $simpla->orders->close(intval($order->id));// Спишем товары
                                //$simpla->notify->email_order_user(intval($order->id));
                                $simpla->notify->email_order_admin(intval($order->id));
                                $logs->log_info('processNotification','status processing "Paid"');
                            }
                            else
                            {
                                $logs->log_info('processNotification',"status don't change");
                                header("HTTP/1.1 200 OK");
                                print $st= 'OK | already paid';
                                return;
                            }
                            break;
                            
                        case 7: // Платеж возращен
	                        $simpla->orders->update_order(intval($order->id), array('note'=>$order->note .' '. "Платеж возращен"));
                            $simpla->orders->update_order(intval($order->id), array('paid'=>0));// Установим статус не оплачен
                            $logs->log_info('processNotification','status processing "Canceled"');
                            break;
                        default:
                            header('HTTP/1.1 400 Bad Request');
                            print $st = 'FAILED | invalid status'; //Ошибка в параметрах
                            $logs->log_error('processNotification','FAILED | Invalid status; Status - '.$status);
                            return;
                    }
                    header("HTTP/1.1 200 OK");
                    print $st= 'OK | the notice is processed';
                    return;
                }
                break;
            
            default:
                header('HTTP/1.1 400 Bad Request');
                print $st = 'FAILED | invalid cmdtype'; //Ошибка в параметрах
                $logs->log_error('processNotification','FAILED | Invalid cmdtype; cmdtype - '.$cmdtype);
                return;
        }

        $logs->log_error('processNotification','POST not received');
        header('HTTP/1.1 400 Bad Request');
        print $st = 'FAILED | the notice is not processed'; //Ошибка в параметрах
    }

    /**
     * 
     * Формирование цифровой подписи
     * 
     * @param array  $signatureParams Список передаваемых параметров
     * @param string $secretWord      Секретное слово
     * @param string $method          Метод формирования цифровой подписи
     * 
     * @return string $hash           Сформированная цифровая подпись
     * 
     */
    public static function computeSignature($signatureParams, $secretWord, $method)
    {
        $normalizedParams = array_change_key_case($signatureParams, CASE_LOWER);
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
                "isamounteditable"
            ),
            "get-details-invoice" => array(
                "token",
                "id"
            ),
            "cancel-invoice" => array(
                "token",
                "id"
            ),
            "status-invoice" => array(
                "token",
                "id"
            ),
            "get-list-invoices" => array(
                "token",
                "from",
                "to",
                "accountno",
                "status"
            ),
            "get-list-payments" => array(
                "token",
                "from",
                "to",
                "accountno"
            ),
            "get-details-payment" => array(
                "token",
                "id"
            ),
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
                "pageview",
                "sessiontimeoutsecs",
                "expirationdate"
            ),
            "card-invoice-form"  =>  array(
                "token",
                "cardinvoiceno"
            ),
            "status-card-invoice" => array(
                "token",
                "cardinvoiceno",
                "language"
            ),
            "reverse-card-invoice" => array(
                "token",
                "cardinvoiceno"
            ),
            "get-qr-code"          => array(
                "token",
                "invoiceid",
                "viewtype",
                "imagewidth",
                "imageheight"
            ),
            "add-web-invoice"      => array(
                "token",
                "serviceid",
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
                "emailnotification",
                "smsphone",
                "returntype",
                "returnurl",
                "failurl"
            ),
            "add-webcard-invoice" => array(
                "token",
                "serviceid",
                "accountno",
                "expiration",
                "amount",
                "currency",
                "info",
                "returnurl",
                "failurl",
                "language",
                "sessiontimeoutsecs",
                "expirationdate",
                "returntype"
            ),
            "response-web-invoice" => array(
                "token",
                "expresspayaccountnumber",
                "expresspayinvoiceno"
            ),
            "notification"         => array(
                "data"
            )
        );
        $apiMethod = $mapping[$method];
        $result = "";
        foreach ($apiMethod as $item) {
            $result .= $normalizedParams[$item];
        }
        
        $hash = strtoupper(hash_hmac('sha1', $result, $secretWord));
        return $hash;
    }
}