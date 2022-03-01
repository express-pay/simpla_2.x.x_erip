<?php
chdir('../../');
require_once('api/Simpla.php');

require_once(dirname(__FILE__).'/ExpressPayEripView.php');
require_once(dirname(__FILE__).'/api/ExpressPayLog.php');
require_once(dirname(__FILE__).'/api/ExpressPayHelper.php');

$logs = new ExpressPayLog();
callback($logs);


/**
 * Функция обработки полученных запросов
 */
function callback($logs){
	$logs->log_info('callback','start processing data from the server');
	$logs->log_info('callback','REQUEST - '.implode(',',$_REQUEST));

	if($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		if(isset($_REQUEST['result']) && $_REQUEST['result'] == 'notify')
		{
			ExpressPayHelper::Notification($logs);
			return;
		}
	}
	else if($_SERVER['REQUEST_METHOD'] == 'GET')
	{
		if(isset($_REQUEST['result']) && $_REQUEST['result'] == 'payment')
		{
			callbackPayment($logs);
			return;
		}
		else if(isset($_REQUEST['result']) && $_REQUEST['result'] == 'fail')
		{
			callbackFail($logs);
			return;
		}
	}
	else{
		$logs->log_error('callback', 'REQUEST_METHOD != "POST" and REQUEST_METHOD != "GET"');        
		header('HTTP/1.1 405 Method Not Allowed');
        print $st = 'FAILED | request method not supported';
	}
}

/**
 * Формирование страницы оплаты для клиента
 */
function callbackPayment($logs){
	$logs->log_info('callbackPayment','Displaying the payment page for the client');
	$simpla = new Simpla();

	$order = $simpla->orders->get_order(intval($_REQUEST['ExpressPayAccountNumber']));
	$settings = $simpla->payment->get_payment_settings($order->payment_method_id);

	$data['status'] = 'payment';
	$data['order_id'] = $_REQUEST['ExpressPayAccountNumber'];
	$data['erip_path'] = $settings['erip_path'];
	
	//Вывод сообщения в шаблон
	$expressPayView = new ExpressPayEripView();
	$expressPayView->design->assign('data', $data);
	print $expressPayView->fetch();
}

/**
 * Формирование страницы ошибки для клиента
 */
function callbackFail($logs){
	$logs->log_info('callbackFail','Displaying the fail payment page for the client');

	$data['status'] = 'fail';
	$data['message'] = 'При выполнении запроса оплаты через ЕРИП произошла непредвиденная ошибка. Пожалуйста, повторите запрос позже или обратитесь в службу технической поддержки магазина.';
	
	//Вывод сообщения в шаблон
	$expressPayView = new ExpressPayEripView();
	$expressPayView->design->assign('data', $data);
	print $expressPayView->fetch();
}