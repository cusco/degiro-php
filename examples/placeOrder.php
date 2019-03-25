<?php

	require_once(__DIR__ . '/../config.php'); // configuration
	require_once(__DIR__ . '/../functions.php'); // global functions

	$ch = curl_init(); // web browser
	$login = webLogin($ch);
	if($login != 200)
		die("login failed\n");

	getDegiroConfig($ch);


	$productId = 4876499;	// ctt
	$qty = 10;	// quantity to sell
	$price = 2.9; // price per share 
	
	placeOrder($ch, $productId, $qty, $price);
	
	