<?php
chdir('../../');
require_once('api/Simpla.php');


require_once(dirname(__FILE__).'/ExpressPayEripView.php');


require_once(dirname(__FILE__).'/api/ExpressPayLog.php');
$logs = new ExpressPayLog();

require_once(dirname(__FILE__).'/api/ExpressPayHelper.php');


callback($logs);//функция обработки полученных запросов


function addInvoice($url,$numberAccount,$amount,$currency,$expiration='',$info='',$surname='',$firstName='',$patronymic='',
$city='',$street='',$house='',$building='',$apartment='',$isNameEditable='',$isAddressEditable='',$isAmountEditable='',$emailNotification='',$smsPhone='')
{
	$logs = new ExpressPayLog();
	//$amount = amountFormat($amount);
	$amount = str_replace( " ", "",$amount);
	$logs->log_info('addInvoice','converting data amount: amount - '.$amount);
	$requestParams = array(
		"accountno"  		=> $numberAccount,
		"amount"     		=> $amount,
		"currency"   		=> $currency,
		"expiration" 	 	=> $expiration,
		"info"              => $info,
		"surname"           => $surname,
		"firstname"         => $firstName,
		"patronymic"        => $patronymic,
		"city"              => $city,
		"street"            => $street,
		"house"             => $house,
		"building"          => $building,
		"apartment"         => $apartment,
		"isnameeditable"    => $isNameEditable,
		"isaddresseditable" => $isAddressEditable,
		"isamounteditable"  => $isAmountEditable,
		"emailnotification" => $emailNotification,
		"smsphone"          => $smsPhone
	);
	$logs->log_info('addInvoice','converting data from json to an array : requestParams - '.implode(' , ',$requestParams));
	foreach($requestParams as $param){
		$param = (isset($param) ? $param : '');
	}
	return sendRequestPOST($url, $requestParams); 
}

function sendRequestPOST($url, $params) {
    $ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function amountFormat($amount){

	$amount_arr = explode(" ",$amount);
	$amount = '';
	foreach($amount_arr as $a){
		$amount .= $a;
	}
	return $amount;

}

function callback($logs){
	
	$logs->log_info('callback','start processing data from the server');

	$logs->log_info('callback','REQUEST - '.implode(',',$_REQUEST));
	

	if($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		if(isset($_REQUEST['result']) && $_REQUEST['result'] == 'notify')
		{
			callbackNotify($logs);
		}
		else{
			callbackPost($logs);
			return;
		}
	}
	else if($_SERVER['REQUEST_METHOD'] == 'GET')
	{
		if(isset($_REQUEST['result']) && $_REQUEST['result'] == 'success')
		{
			callbackSuccess($logs);
		}
		else if(isset($_REQUEST['result']) && $_REQUEST['result'] == 'fail')
		{
			callbackFail($logs);
		}
	}
	else{

		header("HTTP/1.0 200 OK");
		$logs->log_error('callback', '$_SERVER["REQUEST_METHOD"] !== "POST"');
	}


}

function callbackRequest($logs, $data, $simpla){
	$logs->log_info('callbackRequest','processing of notifications from the server');
	$cmdType = $data['CmdType'];
	$status    = $data['Status'];
	$accountNo = $data['AccountNo'];
	$invoiceNo = $data['InvoiceNo'];
	$amount = $data['Amount'];
	$created = $data['Created'];
	$service = $data['Service'];
	$payer = $data['Payer'];
	$address = $data['Address'];

	
	$logs->log_info('callbackRequest','Received POST response; CmdType - '.$cmdType.'; Status - '.$status.'; AccountNo - '.$accountNo
	.'; InvoiceNo - '.$invoiceNo.'; Amount - '.$amount.'; Created - '.$created.'; Service - '.$service.'; Payer - '.$payer.'; Address - '.$address);
	switch($cmdType)
	{
		case 1: 
				$order = $simpla->orders->get_order(intval($accountNo));
				if($order->paid != 1)
				{
					$simpla->orders->update_order(intval($order->id), array('paid'=>1));// Установим статус оплачен
					$simpla->orders->close(intval($order->id));// Спишем товары
					$logs->log_info('callbackRequest','status processing "Paid"');
				}
				else
				{
					$logs->log_info('callbackRequest',"status don't change");
				}
				return;
		case 2: 
				$order = $simpla->orders->get_order(intval($accountNo));
				$simpla->orders->update_order(intval($order->id), array('paid'=>0));// Установим статус не оплачен
				$logs->log_info('callbackRequest','status processing "Canceled"');
				header("HTTP/1.0 200 OK");
				print $st= 'OK | the notice is processed';
				return;
		case 3: 
			break;
	}
	if(isset($status)){
		switch($status){
			case 1: //Ожидает оплату
				$order = $simpla->orders->get_order(intval($accountNo));
				$simpla->orders->update_order(intval($order->id), array('status'=>1));// Установим статус не оплачен
				$logs->log_info('callbackRequest','status processing "Pending payment" ');
				break;
			case 2: //Просрочен
				$logs->log_info('callbackRequest','status processing "Expired" ');
				break;
			case 3://Оплачен
				$order = $simpla->orders->get_order(intval($accountNo));
				if($order->paid != 1)
				{
					$simpla->orders->update_order(intval($order->id), array('paid'=>1));// Установим статус оплачен
					$simpla->orders->close(intval($order->id));// Спишем товары
					$logs->log_info('callbackRequest','status processing "Paid"');
				}
				else
				{
					$logs->log_info('callbackRequest',"status don't change");
				}
				break;
			case 4: //Оплачен частично 
				$logs->log_info('callbackRequest','status processing "Partially paid"');
				break;
			case 5: // Отменен
				$order = $simpla->orders->get_order(intval($accountNo));
				$simpla->orders->update_order(intval($order->id), array('paid'=>0));// Установим статус не оплачен
				$logs->log_info('callbackRequest','status processing "Canceled"');
				break;
			default:
			header("HTTP/1.0 200 OK");
				print $st = 'FAILED | the notice is not processed'; //Ошибка в параметрах
				$logs->log_error('callbackRequest','FAILED | the notice is not processed; Status - '.$status);
				return;
		}
		header("HTTP/1.0 200 OK");
		print $st= 'OK | the notice is processed';
	}
	else{
		$logs->log_error('callbackRequest','POST not received');
		header("HTTP/1.0 200 OK");
		print $st = 'FAILED | the notice is not processed'; //Ошибка в параметрах
	}
}

function callbackPost($logs)
{
	$logs->log_info('callbackPost','billing processing');
	$simpla = new Simpla();
	$message  = '';
	if($_POST){
		$url 			  = $_POST['url'];
		$numberAccount    = $_POST['accountNo'];
		$amount           = $_POST['amount'];
		$currency         = $_POST['currency'];
		//$expiration    
		$info             = $_POST['info'];
		$surname          = $_POST['surname'];
		$firstName        = $_POST['firstname'];
		$patronymic		  = $_POST['patronymic'];
		//$city
		//$street
		//$house
		//$building
		//$apartment
		$isNameEditable    = $_POST['IsNameEditable'];
		$isAddressEditable = $_POST['IsAddressEditable'];
		$isAmountEditable  = $_POST['IsAmountEditable'];
		$emailNotification = $_POST['EmailNotification'];
		$smsPhone          = $_POST['SmsPhone'];
		
		if(!isset($url) || $url == '' ){
			$logs->log_error('callbackPost','$url is null');
			return;
		}

		$logs->log_info('callbackPost','retrieving data from a POST request; url - '.$url.'; numberAccount - '.$numberAccount.'; amount - '.$amount
						.'; currency - '.$currency.'; surname - '.$surname.'; firstName - '.$firstName.' patronymic - '.$patronymic.'; isNameEditable - '.$isNameEditable
						.'; isAddressEditable - '.$isAddressEditable.'; isAmountEditable - '.$isAmountEditable.'; emailNotification - '.$emailNotification
						.'; smsPhone - '.$smsPhone);

				$expiration = '';
				$info = isset($info) ? $info : '';
				$city = '';
				$street = '';
				$house = '';
				$building='';
				$apartment='';

		$response          = addInvoice($url,$numberAccount,$amount,$currency
							,$expiration,$info,$surname,$firstName,$patronymic
							,$city,$street,$house,$building,$apartment,$isNameEditable
							,$isAddressEditable,$isAmountEditable,$emailNotification,$smsPhone); //Формирование и отправка данных на сервер, получает ответ в json формате

		$logs->log_info('callbackPost','Received response from the server; response - '.$response);

		$data = array();

		try {

			$data = json_decode($response,true);//Преобразование ответа из json в array
			$logs->log_info('callbackPost','converting data from json to an array : Data - '.implode(',',$data));

		} catch(Exception $e) {

			$logs->log_error('callbackPost', "Fail to parse the server response; RESPONSE - " . $response);

		}
		$response          = $data;

		if(isset($response['InvoiceNo']))
		{
			$message = '
					<div style="text-align:center;">
						<div style="color:#00a12d; font-size:20px; font-weight:bold;">Счет добавлен в систему ЕРИП для оплаты.</div>
						<div style="color:#00a12d; font-size:20px; font-weight:bold; padding-bottom:15px;">Номер заказа для оплаты: '.$numberAccount.' </div>	
					</div>';
		}
		else{
			$message = '
				<div style="text-align:center;">
					<div style="color:#D1001D; font-size:20px; font-weight:bold; padding-bottom:15px;">При выполнении запроса произошла непредвиденная ошибка. Пожалуйста, повторите запрос позже или обратитесь в службу технической поддержки магазина</div>
				</div>';
		}
	}
	else{
		$message = '
		<div style="text-align:center;">
			<div style="color:#D1001D; font-size:20px; font-weight:bold; padding-bottom:15px;">При выполнении запроса произошла непредвиденная ошибка. Пожалуйста, повторите запрос позже или обратитесь в службу технической поддержки магазина</div>
		</div>';
	}

	$logs->log_info('callbackPost','display result');

	$expressPayView = new ExpressPayEripView();

	$data['message'] = $message;

	//Вывод сообщения в шаблон
	$expressPayView->design->assign('data', $data);

	print $expressPayView->fetch();
}

function notify_fail($dataJSON, $signature='', $computeSignature='', $secret_key='') {
	$this->log_error('notify_fail', "Fail to update status; RESPONSE - " . $dataJSON . ', signature - ' . $signature . ', Compute signature - '. $computeSignature . ', secret key - ' . $secret_key);
}

function callbackNotify($logs)
{
	$dataJSON 			= ( isset($_REQUEST['Data']) ) ? htmlspecialchars_decode($_REQUEST['Data']) : '';//Получение данных в json формате
	$dataJSON 			= stripcslashes($dataJSON);
	$logs->log_info('callback','received data from the server : Data - '.$dataJSON);
	$signature 		= ( isset($_REQUEST['Signature']) ) ? $_REQUEST['Signature'] : ''; //Получение подписи
	$logs->log_info('callback','receipt of a signature : signature - '.($signature !== '' ? $signature : 'no signature'));
	$data = array();
	try {
		$data = json_decode($dataJSON,true); //Преобразование из json в array
		$logs->log_info('callback','converting data from json to an array : Data - '.implode(',',$data));
	} catch(Exception $e) {
		$this->log_error('callback', "Fail to parse the server response; RESPONSE - " . $dataJSON);
		$this->notify_fail($dataJSON);
	}
	if(isset($data['AccountNo'])){
		$simpla = new Simpla();
		
		$order          = $simpla->orders->get_order(intval($data['AccountNo']));//Получение заказа по его номеру
		$logs->log_info('callback','receiving an order by AccountNo; AccountNo - '.$data['AccountNo'].'; Order - '.($order == null ? "is null" : $order->id));
		
		$payment_method = $simpla->payment->get_payment_method($order->payment_method_id);//Получние метода оплаты
		
		$settings		= $simpla->payment->get_payment_settings($order->payment_method_id);//Полечение настрек для данного метода
		$logs->log_info('callback','getting settings; Settings - '.json_encode($settings));
		
		if(isset($settings['is_use_signature_notify']) && $settings['is_use_signature_notify']) //Проверка на использование цифровой подписи
		{
			$helper = new ExpressPayHelper();

			$sign = $helper->compute_signature($dataJSON, $settings['secret_key']); //Генерация подписи из полученных данных
			$logs->log_info('callback','calculated signature; signature - '.$sign);
			
			if($signature == $sign)//проверка на совпадение подписей
			{
				$logs->log_info('callback','Signatures match');
				callbackRequest($logs,$data,$simpla);
				return;
			}
			else
			{
				$logs->log_error('callback', 'signatures do not match; Received signature - '.$signature.'; Calculated signature - '.$sign);
			}
		}
		else
		{
			$logs->log_info('callback','signature is not verified');
			callbackRequest($logs,$data,$simpla);
			return;
		}
	}
	else
	{
		notify_fail($dataJSON);
	}
	header("HTTP/1.0 200 OK");
	print $st = 'FAILED | the notice is not processed'; //Ошибка в параметрах
}

function callbackSuccess($logs)
{
	$logs->log_info('callbackSuccess','billing processing');
	$simpla = new Simpla();

	$data['message'] = '<div style="text-align:center;">
							<div style="color:#00a12d; font-size:20px; font-weight:bold;">Счет добавлен в систему ЕРИП для оплаты.</div>
							<div style="color:#00a12d; font-size:20px; font-weight:bold; padding-bottom:15px;">Номер заказа для оплаты: '.$_REQUEST['ExpressPayAccountNumber'].' </div>	
						</div>';

	

	$expressPayView = new ExpressPayEripView();
	//Вывод сообщения в шаблон
	$expressPayView->design->assign('data', $data);

	print $expressPayView->fetch();
}

function callbackFail($logs)
{
	$logs->log_info('callbackFail','billing processing');
	$simpla = new Simpla();

	$data['message'] = '
		<div style="text-align:center;">
			<div style="color:#D1001D; font-size:20px; font-weight:bold; padding-bottom:15px;">При выполнении запроса произошла непредвиденная ошибка. Пожалуйста, повторите запрос позже или обратитесь в службу технической поддержки магазина</div>
		</div>';

	$expressPayView = new ExpressPayEripView();
	//Вывод сообщения в шаблон
	$expressPayView->design->assign('data', $data);

	print $expressPayView->fetch();
}