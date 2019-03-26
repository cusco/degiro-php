<?php

//function checkOrdersToSell($ch, $text, $issueId, $productId, $qty, $cost, $trySell, $trySellUnit){
function trySell($ch, $p, $trySell){
	/* * *
	 *	Check if the product is good to sell
	 *
	 */
	global $config;
	$debug = $config['debug'];

	$issueId	= $p['vwdId'];
	$productId	= $p['id'];
	$name		= $p['name'];
	$symbol		= $p['symbol'];
	$qty		= $p['qtyAvail'];
	$cost		= $p['breakEvenPrice'];

	$info		= getTradingInfo($ch, $issueId);

	$last		= (float) $info['last'];
	$prev		= (float) $info['prev'];
	$totalCost	= (float) ($cost * $qty);
	$lastPrice	= (float) $info['lastPrice'];
	$totalLast	= (float) $lastPrice * $qty;
	$diff		= (float) ($lastPrice * $qty) - ($totalCost);
	$trNr		= $info['trNr'];

	$trySellUn	= normalizeFloat($trySell/$qty, 4);

	if($debug){
		echo date('Y-m-d H:i:s') . "|Checking order $symbol trying to sell at $trySell ($trySellUn), ($trNr transactions today)";
	}

	#echo "forecast $text: $last, f1: $forecast1, f2: $forecast2|" . round(($forecast1 - $last),4) . "\n";

	if($last == 0 && $prev == 0){
		echo "|no data\n";
		return;
	}
	if($last > $prev){	// still rising, lets wait
		if($debug){
			echo "|$last > $prev ... still rising, skipping\n";
			return;
		}
	}

	#placeOrder($ch, $productId, $qty, 3.5);
	if($trySell <= $totalLast){ // at least 1€ for comissions
		if($debug)
			echo "|lastPrice $lastPrice ($totalLast) - placing order!!\n";
		else
			$sellingPrice = max($lastPrice, $trySellUn);
			echo date('Y-m-d H:i:s') . "|Placing order for $text to sell at $trySell ($qty * $sellingPrice)\n";
			placeOrder($ch, $productId, $qty, $sellingPrice);
	}else{
		if($debug)
			echo "|not high enough to sell. lastPrice $lastPrice ($totalLast) diff: $diff\n";
		#echo date('Y-m-d H:i:s') . "|$text not high enough to sell ($lastPrice) ($diff)\n";
	}

}


function placeOrder($ch, $productId, $qty, $price){
	#$productId = 4876499;
	#$qty = 15;
	#$price = (float)3.5;
	$price = (float) $price;

	$url = 'https://trader.degiro.nl/trading/secure/v5/checkOrder;jsessionid=' . sessionId . '?intAccount=' . intAccount . '&sessionId=' . sessionId;
	$postParams = '{"buySell":"SELL","orderType":0,"productId":"' . $productId . '","timeType":1,"size":' . $qty . ',"price":' . $price . '}';

	$headers[] = 'Content-Type: application/json;charset=UTF-8';
	$headers[] = 'Accept: application/json, text/plain, */*';

	curl_setopt_array($ch, [
		CURLOPT_URL				=> $url,
		CURLOPT_HTTPHEADER		=> $headers,
		CURLOPT_POST			=> true,
		CURLOPT_POSTFIELDS		=> $postParams,
	]);

	$result = curl_exec($ch);
	if($result != json_decode(json_encode($result), true)){
		die("invalid json\n");
	}
	$result = json_decode($result, true);

	if($result['status'] == 0){
		$confirmationId = $result['confirmationId'];
		confirmOrder($ch, $confirmationId, $postParams, $productId, $qty);
	}else{
		echo "Error placing order, check result\n";
		var_dump($result);
	}
}

function confirmOrder($ch, $confirmationId, $postParams, $productId, $qty){
	//global $runFile, $selling;
	$url = 'https://trader.degiro.nl/trading/secure/v5/order/' . $confirmationId . ';jsessionid=' . sessionId . '?intAccount=' . intAccount . '&sessionId=' . sessionId;
	curl_setopt_array($ch, [
		CURLOPT_URL				=> $url,
		CURLOPT_POST			=> true,
		CURLOPT_POSTFIELDS		=> $postParams,
	]);

	$result = curl_exec($ch);
	if($result != json_decode(json_encode($result), true)){
		die("invalid json\n");
	}
	$result = json_decode($result, true);
	if(isset($result['errors'])){
		echo "Error confirming order, check result\n";
	}
	var_dump($result);
	if($result['status'] == 0){
		/*
		$wrote = 0;
		foreach($selling as $k => $v){
			if($v['productId'] == $productId){
				$wrote = 1;
				$selling[$k]['qty'] = ($selling[$k]['qty'] - $qty);
			}
		}
		if($wrote){
			file_put_contents($runFile, json_encode($selling));
		}else{
			file_put_contents($runFile, "ERROR: could not find $productId" . json_encode($selling));
		}
		*/
	}
}


function updatePortfolio($ch){
	global $config;
	$userToken = clientId;
	$intAccount = intAccount;
	$sessionId = sessionId;
	$cookieFile = $config['cookieFile'];
	#$force=0;
	
	$i=0;
	start:

	$header = array(
		 'authority: trader.degiro.nl'
		,'cache-control: max-age=0'
		,'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8'
		,'accept-encoding: gzip, deflate, br'
		,'accept-language: en-GB,en;q=0.9,pt-PT;q=0.8,pt;q=0.7'
		,'cache-control: max-age=0'
		,'upgrade-insecure-requests: 1'
		,'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36'
	);

	$url = tradingUrl . "v5/update/$intAccount;jsessionid=$sessionId?portfolio=0";

	curl_setopt_array($ch, [
		CURLOPT_URL				=> $url,
		CURLOPT_HTTPHEADER		=> $header,
		CURLOPT_RETURNTRANSFER	=> true,
		CURLOPT_COOKIEFILE		=> $cookieFile,
		CURLOPT_COOKIEJAR		=> $cookieFile,
		CURLOPT_POST			=> false,
		CURLOPT_HTTP_VERSION	=> CURL_HTTP_VERSION_2_0,
		CURLOPT_ENCODING		=> '',
	]);
	$result = curl_exec($ch);
	$info = curl_getinfo($ch);
	if($info['http_code'] != 200 && $info['http_code'] != 201){
		echo "error getting portfolio.. lets use cache (or remove cookie file to reload)\n";
		var_dump($info);
		var_dump($result);
		var_dump($sessionId);
		var_dump($url);
		die();
		#if($force && $i <3){
		#	$i++;
		#	webLogin($ch, $force);
		#	goto start;
		#}else{
		#	// lets use cache for now
			$result = file_get_contents(__DIR__ . '/portfolio.json'); 
		#}
	}
	$result = json_decode($result,true);
	$portfolio = array();
	$cash = false;
	$searchProductIds = array();
	$openOrders = getOpenOrders($ch);

	foreach($result['portfolio']['value'] as $k => $p){
		$productId = $p['id'];

		if(is_numeric($productId)){
			if(isset($p['value'])){
				foreach ($p['value'] as $info){
					if(isset($info['value'])){
						$name = $info['name'];
							$portfolio["$productId"][$name] = $info['value'];
					}
				}
			}
		}
	}

	foreach($portfolio as $k => $p){
		if($p['size'] == 0){
			unset($portfolio[$k]);
			continue;
		}
		if($p['positionType'] == 'CASH'){
			unset($portfolio[$k]);
			$cash = $p;
			continue;
		}
		$s = array_search($k, array_column($openOrders, 'productId')); // search if this product id is in a openOrder
		if($s !== false){
			$portfolio["$k"]['qtyAvail'] = $p['size'] - $openOrders["$s"]['size'];
		}else{
			$portfolio["$k"]['qtyAvail'] = $p['size'];
		}
		$searchProductIds[] = $k;
	}
	$productInfo = getProductInfo($ch, $searchProductIds);
	foreach($portfolio as $k => $p){
		$portfolio["$k"]['name'] = $productInfo['data']["$k"]['name'];
		$portfolio["$k"]['vwdId'] = $productInfo['data']["$k"]['vwdId'];
		$portfolio["$k"]['symbol'] = $productInfo['data']["$k"]['symbol'];
	//	$portfolio["$k"]['isin'] = $productInfo['data']["$k"]['isin'];
	}

	#if($force){
		$cache = json_encode($portfolio, JSON_PRETTY_PRINT);
		file_put_contents(__DIR__ . '/portfolio.json', json_encode($cache, JSON_PRETTY_PRINT));
	#}

	return $portfolio;
}

function getOpenOrders($ch){
	global $config;
	$intAccount = intAccount;
	$sessionId = sessionId;
	$cookieFile = $config['cookieFile'];

	$url = $url = tradingUrl . "v5/update/$intAccount;jsessionid=$sessionId?orders=0";
	curl_setopt_array($ch, [
		CURLOPT_URL				=> $url,
	]);
	$result = curl_exec($ch);
	$result = json_decode($result,true);

	//return $result['orders']['value'];
	$orders = array();

	foreach($result['orders']['value'] as $k => $order){
		foreach($order['value'] as $o){
			$name = $o['name'];
			$value = $o['value'];
			$orders["$k"]["$name"] = $value;
		}
	}
	return $orders;
}

function checkProspects($ch, $zone){
	// NASDAQ
	// https://trader.degiro.nl/product_search/secure/v5/stocks?exchangeId=663&stockCountryId=846&offset=0&limit=500&requireTotal=true&sortColumns=name&sortTypes=asc
	$userToken = clientId;
	$intAccount = intAccount;
	$sessionId = sessionId;

	$url = "https://trader.degiro.nl/product_search/secure/v5/stocks?exchangeId=663&stockCountryId=846&offset=0&limit=500&requireTotal=true&sortColumns=name&sortTypes=asc&intAccount=$intAccount&sessionId=$sessionId";
	if($zone == 'pt'){
		$url = "https://trader.degiro.nl/product_search/secure/v5/stocks?stockCountryId=954&&offset=0&limit=500&requireTotal=true&sortColumns=name&sortTypes=asc&intAccount=$intAccount&sessionId=$sessionId";
	}
	curl_setopt_array($ch, [
		CURLOPT_URL		=> $url,
		CURLOPT_POST	=> false,
	]);
	$result = curl_exec($ch);
	$result = json_decode($result,true);
	#var_dump($url);
	#var_dump($result);

	#var_dump($result);
	foreach($result['products'] as $p){
		#$name = ""
		#$res[] = $p['closePrice'];
		if(isset($p['closePrice']) && (float)$p['closePrice'] < 1){
			#echo $p['closePrice'] . '|' . $p['name'] . "\n";

			$name = $p['name'];
			$closed = $p['closePrice'];
			$issueId = $p['vwdId'];
			$productId = $p['id'];

			$res["$name"] = array('name' => $name, 'closed' => $closed, 'issueId' => $issueId, 'productId' => $productId);
		}
	}
	#var_dump($res);

	foreach($res as $k => $v){
		$info = getTradingInfo($ch, $v['issueId']);
		if(!isset($info['lowPrice']) || !is_float($info['lowPrice'])){
			continue;
		}
		#var_dump($info);
		$lowP = $info['lowPrice'];
		$highP = $info['highPrice'];
		$last = $info['last'];
		$trNr = $info['trNr'];
		$avg = $info['avg'];

		if($last > 0 && $lowP > 0 && $info['lowPrice']){
			$diff = $highP - $lowP;
			echo "$diff|$trNr|$avg|" . $v['name'] . "|$lowP|$highP\n";
		}
	}
}

function getProductInfo($ch, $productIds){

	$userToken = clientId;
	$intAccount = intAccount;
	$sessionId = sessionId;

	if(count($productIds) < 1){
		return array();
	}

	$url = productSearchUrl . "v5/products/info?intAccount=$intAccount&sessionId=$sessionId";
	$params = '["' . implode('","', $productIds) . '"]';

	$header = array(
		 'Origin: https://trader.degiro.nl'
		,'Content-Type: application/json;charset=UTF-8'
	);

	curl_setopt_array($ch, [
		CURLOPT_URL				=> $url,
		CURLOPT_HTTPHEADER		=> $header,
		CURLOPT_POST			=> true,
		CURLOPT_POSTFIELDS		=> $params,
	]);
	$result = curl_exec($ch);
	$info = curl_getinfo($ch);
	if($info['http_code'] != 200){
		return array();
	}
	return json_decode($result, true);
}

function getTradingInfo($ch, $issueId){
	$userToken = clientId;
	$intAccount = intAccount;
	$sessionId = sessionId;

	$productId = 0;

	$url = "https://charting.vwdservices.com/hchart/v1/deGiro/data.js?requestid=1&resolution=PT1M&culture=en-US&period=P1D&series=issueid:$issueId&series=price:issueid:$issueId&format=json&userToken=$userToken&tz=Europe/Lisbon";
	#$url = "https://charting.vwdservices.com/hchart/v1/deGiro/data.js?requestid=1&resolution=PT1M&culture=en-US&period=P1W&series=issueid:$issueId&series=price:issueid:$issueId&format=json&userToken=$userToken&tz=Europe/Lisbon";

	curl_setopt_array($ch, [
		CURLOPT_URL		=> $url,
		CURLOPT_POST	=> false,
	]);
	$result = curl_exec($ch);
	$result = json_decode($result,true);

	$prev = 0;
	$last = 0;
	if(isset($result['series'][1]['data'])){
		$trNr = count($result['series'][1]['data']); // trading volume today
	}else{
		return array();
		$trNr = 0;
	}

	foreach($result['series'][1]['data'] as $k => $v){
		$v0 = $v[0];
		$v1 = $v[1];
		$samples[] = array($v0);
		$labels[] = $v1;
	}
	if($trNr > 1)
		$avg = array_sum(array_filter($labels))/$trNr;
	else
		$avg = 0;

	if($trNr > 1){
		$prev = array_slice($result['series'][1]['data'], -2, 1)[0][1];
		$last = array_slice($result['series'][1]['data'], -1)[0][1];
	}
	#var_dump($url);
	#var_dump($result['series'][1]['data']);
	#echo "prev: $prev\n";
	#echo "last: $last\n";


	$ret = array(
		'quality'	=> $result['series'][0]['data']['quality'],
		'lastPrice'	=> $result['series'][0]['data']['lastPrice'],
		'lastTime'	=> $result['series'][0]['data']['lastTime'],
		'issueId'	=> $result['series'][0]['data']['issueId'],
		'lowPrice'	=> $result['series'][0]['data']['lowPrice'],
		'highPrice'	=> $result['series'][0]['data']['highPrice'],
	//	'forecast1'	=> $p1,
	//	'forecast2'	=> $p2,
		'prev'		=> $prev,
		'last'		=> $last,
		'trNr'		=> $trNr,
		'avg'		=> $avg,
	);


	return $ret;
}

function getDegiroConfig($ch){
	$url = 'https://trader.degiro.nl/login/secure/config';
	$header=array();
	$cookieFile = __DIR__ . '/cookie.txt';
	curl_setopt_array($ch, [
		CURLOPT_URL				=> $url,
		CURLOPT_HTTPHEADER		=> $header,
		CURLOPT_RETURNTRANSFER	=> true,
		CURLOPT_COOKIEFILE		=> $cookieFile,
		CURLOPT_COOKIEJAR		=> $cookieFile,
		CURLOPT_POST			=> false,
		CURLOPT_HTTP_VERSION	=> CURL_HTTP_VERSION_2_0,
		CURLOPT_ENCODING		=> '',
	]);

	$result = curl_exec($ch);
	$info = curl_getinfo($ch);
	if($info['http_code'] != 200){
		die('could not get config');
	}

	//$result = (gzdecode($result));

	// verify json
	if($result != json_decode(json_encode($result), true)){
			die("invalid json\n");
	}
	$result = json_decode($result, true);

	$clientId = $result['clientId'];

	define('clientId', $result['clientId']);
	define('sessionId', $result['sessionId']);
	define('paUrl', $result['paUrl']);
	define('productSearchUrl', $result['productSearchUrl']);
	define('tradingUrl', $result['tradingUrl']);
	#var_dump($result);
	$paUrl = paUrl . 'client?sessionId=' . sessionId;

	curl_setopt($ch, CURLOPT_URL, $paUrl);
	$result = curl_exec($ch);
	//$result = (gzdecode($result));

	if($result != json_decode(json_encode($result), true)){
			die("invalid json\n");
	}
	$result = json_decode($result, true);

	define('intAccount', $result['data']['intAccount']);
	return $info['http_code'];

}

function webLogin($ch){
	/* also login */

	global $config;
	$cookieFile = $config['cookieFile'];
	#if($force){
	#	unlink($cookieFile);
	#}
	if(!file_exists($cookieFile)){
		touch($cookieFile);
	}
	$header = array(
		 'Origin: https://trader.degiro.nl'
		,'Accept-Encoding: gzip, deflate, br'
		,'Accept-Language: en-GB,en;q=0.9,pt-PT;q=0.8,pt;q=0.7'
		,'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36'
		,'Content-Type: application/json;charset=UTF-8'
		,'Accept: application/json, text/plain, */*'
		,'Referer: https://trader.degiro.nl/login/'
		,'Authority: trader.degiro.nl'
		,'Dnt: 1'
	);

	// check if we're still logged in
	$url = 'https://trader.degiro.nl/login/secure/config';
	curl_setopt_array($ch, [
		CURLOPT_URL				=> $url,
		CURLOPT_HTTPHEADER		=> $header,
		CURLOPT_RETURNTRANSFER	=> true,
		CURLOPT_COOKIEFILE		=> $cookieFile,
		CURLOPT_COOKIEJAR		=> $cookieFile,
		CURLOPT_HTTP_VERSION	=> CURL_HTTP_VERSION_2TLS,
		CURLOPT_POST			=> false,
	//	CURLOPT_POSTFIELDS		=> $params,
		CURLOPT_ENCODING		=> '',
	]);

	$result = curl_exec($ch);
	$info = curl_getinfo($ch);


	if($info['http_code'] != 200){ // We need to login now
		curl_close($ch); // for some reason...
		$ch = curl_init();

		// login parameters
		$username = $config['username'];
		$password = $config['password'];
		$params = '{"username":"' . $username . '","password":"' . $password . '","isPassCodeReset":false,"isRedirectToMobile":false,"queryParams":{}}';
		echo "logging in...\n";

		$url = 'https://trader.degiro.nl/login/secure/login';

		$headers = array();
		$headers[] = 'Origin: https://trader.degiro.nl';
		$headers[] = 'Accept-Encoding: gzip, deflate, br';
		$headers[] = 'Accept-Language: en-GB,en;q=0.9,pt-PT;q=0.8,pt;q=0.7';
		$headers[] = 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36';
		$headers[] = 'Content-Type: application/json;charset=UTF-8';
		$headers[] = 'Accept: application/json, text/plain, */*';
		$headers[] = 'Referer: https://trader.degiro.nl/login/pt';
		$headers[] = 'Authority: trader.degiro.nl';
		$headers[] = 'Dnt: 1';

		curl_setopt_array($ch, [
			CURLOPT_URL			=> $url,
			CURLOPT_RETURNTRANSFER		=> true,
			CURLOPT_HTTPHEADER		=> $headers,
			CURLOPT_COOKIEFILE		=> $cookieFile,
			CURLOPT_COOKIEJAR		=> $cookieFile,
			CURLOPT_POST			=> true,
			CURLOPT_POSTFIELDS		=> $params,
			CURLOPT_ENCODING		=> '',
			//CURLINFO_HEADER_OUT		=> 1, // debug
		]);
		$result = curl_exec($ch);
		$info = curl_getinfo($ch);
		#var_dump($info);
		#var_dump($result);
	}
	return $info['http_code'];
}

function checkJson($json){
	if($json != json_decode(json_encode($json), true)){
		if($debug)
			var_dump($json);
		return false;
	}
	return true;
}

function normalizeFloat($value, $tick){
	$tick = (int) $tick; // qty of zeros
	if($tick < 1){
		die("invalid normalization tick\n");
	}
	$base = 10 ** $tick;

	$value= round((float)($value), $tick) * $base;
	$value= ($value - ($value %5))/$base;	// last tick must end in 5 or 0

	return $value;

}
