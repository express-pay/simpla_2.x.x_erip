<?php 
require_once('api/Simpla.php');
require_once(dirname(__FILE__).'/api/ExpressPayHelper.php');
require_once(dirname(__FILE__).'/api/ExpressPayLog.php');

class ExpressPayErip extends Simpla
{
    public function checkout_form($order_id, $button_text = null)
	{  
        $logs = new ExpressPayLog();

        $logs->log_info('checkout_form','initialization checkout_form');
        if(empty($button_text)){
            $button_text = 'Перейти к оплате';
        }

        $order          = $this->orders->get_order(intval($order_id));                      //Получение заказа по его id
        $payment_method = $this->payment->get_payment_method($order->payment_method_id);    //Получение метода оплаты по его id, который был выбран для оплаты
        $settings		= $this->payment->get_payment_settings($order->payment_method_id);  //Получение настроек для метода оплаты
        $currency       = $this->money->get_currency(intval($payment_method->currency_id)); //Получение валюты для последующей проверки кода валюты 

        $logs->log_info('checkout_form','$currency->code = ' . $currency->code);
        if($currency->code != "BYN" && $currency->code != 933 && $currency->code != 974 && $currency->code != 'BYR')//Проверка кода валюты
            return '<div class="message_error">Ошибка! Оплата может быть поизведена только в BYN!</div>';

        $token          = $settings['token'];       //API-ключ производителя услуг
        $serviceId      = $settings['service_id'];  //Номер услуги производителя услуг
        $url            = (($settings['test_mode'])? $settings['url_sandbox_api'] : $settings['url_api'])
                        .'/web_invoices';           //Составление адреса, по которому будет отправлен запрос на выставление счета
                        
        $accountNo      = intval($order_id);        //Номер лицевого счета

        //Сумма счета на оплату. Разделителем дробной и целой части является символ запятой
        $amount         = str_replace( " ", "",$this->money->convert($order->total_price, $payment_method->currency_id, true)); 
        
        $info           = str_replace('##order_id##',$order_id,$settings['info']); //Назначение платежа
        $fio            = explode(" ",$order->name);
        $surname        = trim($fio[0]);//Фамилия
        $first_name     = trim($fio[1]);//Имя
        $patronymic     = trim($fio[2]);//Отчество
        $name_edit      = (isset($settings['name_editable'])    ? 1 : 0);// При оплате разрешено изменять ФИО плательщика 0 – нет, 1 – да
        $address_edit   = (isset($settings['address_editable']) ? 1 : 0);//	При оплате разрешено изменять адрес плательщика 0 – нет, 1 – да
        $amount_edit    = (isset($settings['amount_editable'])  ? 1 : 0);// При оплате разрешено изменять сумму оплаты 0 – нет, 1 – да

        if(isset($settings['is_send_client_email']) && $settings['is_send_client_email'])
            $email_notif    = $order->email;//Адрес электронной почты, на который будет отправлено уведомление о выставлении счета
        if(isset($settings['is_send_client_sms']) && $settings['is_send_client_sms']){
            $sms_phone      = $order->phone;//Номер мобильного телефона, на который будет отправлено SMS-сообщение о выставлении счета
            $sms_phone = str_replace('+','',$sms_phone);
            $sms_phone = str_replace(' ','',$sms_phone);
            $sms_phone = str_replace('-','',$sms_phone);
            $sms_phone = str_replace('(','',$sms_phone);
            $sms_phone = str_replace(')','',$sms_phone);
        }

        $logs->log_info('checkout_form','data to send; url - '.$url.'; numberAccount - '.$accountNo.'; amount - '.$amount
						.'; currency - '.$currency->code.'; surname - '.$surname.'; firstName - '.$first_name.'; patronymic - '.$patronymic.'; isNameEditable - '.$name_edit
						.'; isAddressEditable - '.$address_edit.'; isAmountEditable - '.$amount_edit.'; emailNotification - '.$email_notif
                        .'; smsPhone - '.$sms_phone);
                        
        $return_url = $this->config->root_url.'/payment/ExpressPayErip/callback.php?result=payment';//Адрес для перенаправления пользователя в случае успешного создания счёта
        $fail_url   = $this->config->root_url.'/payment/ExpressPayErip/callback.php?result=fail';//Адрес для перенаправления пользователя в случае неуспешной создания счёта
        /*
            ServiceId 	Integer 	Номер услуги
            AccountNo 	String(30) 	Номер лицевого счета
            Amount 	Decimal(19,2) 	Сумма счета на оплату. Разделителем дробной и целой части является символ запятой
            Currency 	Integer 	Код валюты
            Signature 	String 	Цифровая подпись
            ReturnType 	String 	Тип ответа. Может принимать два значения:

                Redirect - перенаправляет пользователя по заданным адресам ReturnUrl или FailUrl. При выборе данного типа ответа
                Json - возвращает результат операции в формате json

            ReturnUrl 	String 	Адрес, на который происходит перенаправление после успешного выставления счета
            FailUrl 	String 	Адрес, на который происходит перенаправление при ошибке выставления счета
            Expiration 	String(8) 	Дата истечения срока действия выставлена счета на оплату. Формат - yyyyMMdd
            Info 	String(1024) 	Назначение платежа
            Surname 	String(30) 	Фамилия
            FirstName 	String(30) 	Имя
            Patronymic 	String(30) 	Отчество
            Street 	String(30) 	Улица
            House 	String(18) 	Дом
            Street 	String(18) 	Улица
            Building 	String(10) 	Корпус
            Apartment 	String(10) 	Квартира
            IsNameEditable 	Integer 	При оплате разрешено изменять ФИО плательщика 0 – нет, 1 – да
            IsAddressEditable 	Integer 	При оплате разрешено изменять адрес плательщика 0 – нет, 1 – да
            IsAmountEditable 	Integer 	При оплате разрешено изменять сумму оплаты 0 – нет, 1 – да
            EmailNotification 	String(255) 	Адрес электронной почты, на который будет отправлено уведомление о выставлении счета
            SmsPhone 	String(13) 	Номер мобильного телефона, на который будет отправлено SMS-сообщение о выставлении счета
            ReturnInvoiceUrl 	Integer 	Вернуть в ответе публичную ссылку на счет
            0 – нет, 1 – да (0 - по умолчанию)
            (Примечание: только для случая, когда ReturnType равен 2 (Json)) 
        */

        $secret_word = $settings['secret_key'];//Секретное слово для подписи счетов (Задается в панели express-pay.by)
        $logs->log_info('checkout_form','getting a secret word; secret_word - '.$secret_word);

        $request_params = array(
            'ServiceId'         => $serviceId,
            'AccountNo'         => $accountNo,
            'Amount'            => $amount,
            'Currency'          => '933',
            'ReturnType'        => 'redirect',
            'ReturnUrl'         => $return_url,
            'FailUrl'           => $fail_url,
            'Expiration'        => '',
            'Info'              => $info,
            'Surname'           => $surname,
            'FirstName'         => $first_name,
            'Patronymic'        => $patronymic,
            'Street'            => '',
            'House'             => '',
            'Apartment'         => '',
            'IsNameEditable'    => $name_edit,
            'IsAddressEditable' => $address_edit,
            'IsAmountEditable'  => $amount_edit,
            'EmailNotification' => $email_notif,
            'SmsPhone'          => $sms_phone
        );
        $request_params['Signature'] = ExpressPayHelper::computeSignature($request_params, $secret_word, 'add-web-invoice');
        
        $button = '<form method="POST" action="'.$url.'">';

        foreach($request_params as $key => $value)
        {
            $button .= "<input type='hidden' name='$key' value='$value'/>";
        }

        $button .= '<input type="submit" class="checkout_button" name="submit_button" value="'.$button_text.'" />';
        $button .= '</form>';

        $logs->log_info('checkout_form','getting a button; button - '.$button);
        
        return $button;
    }
}
?>