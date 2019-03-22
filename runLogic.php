<?php

	/* * *
	 *	Do not edit this unless you know what you're doing
	 *	Do check the file config.php
	 */
	require_once(__DIR__ . '/config.php'); // configuration
	require_once(__DIR__ . '/functions.php'); // global functions

	$ch = curl_init(); // web browser

	// login
	$login = webLogin($ch);
	if($login != 200)
		die("login failed\n");

	// get session stuff
	getDegiroConfig($ch);


	$portfolio = updatePortfolio($ch);	// check what we have to sell

	// portfolio checks
	foreach($portfolio as $k => $p){
		if($p['qtyAvail'] == 0)	// skip if there are none available to sell
			unset($portfolio[$k]);
	}
	$count = count($portfolio);
	if($count < 1){
		echo date('Y-m-d H:i:s') . "|nothing to sell\n";
		die();
	}

	echo date('Y-m-d H:i:s') . "|found $count orders to check...\n";

	/**** real stuff happening here ****/
	foreach($portfolio as $k => $v){

		$qty		= $v['qtyAvail'];
		$cost		= $v['breakEvenPrice'];
		$comission	= abs($v['realizedProductPl']);

		$totalCost	= (float)($cost * $qty) + ($comission *2); // add another commission for the selling transaction
		$trySell 	= $totalCost + 0.3;
		$trySell	= normalizeFloat($trySell, 4);	// round
		#$trySellUn	= $trySell/$qty;
		#$trySellUn	= normalizeFloat($trySellUn, 4);
		
		trySell($ch, $v, $trySell);
	
		#checkOrdersToSell($ch, $text, $issueId, $productId, $qty, $cost, $trySell, $trySellUnit);
	}
	
	
