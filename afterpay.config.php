
// call afterpay configuration using curl 
$afterpayURL = "https://api-sandbox.afterpay.com/v1/configuration";
$apikey = base64_encode('40351'.':'.'5287f73ac23c6199705e0c9310537df1017a5a3e19361069dc2578ced0ab99524adaf94e34e55a64e4ff6c47742a3fcf0c4b4df5e624490a81be65d34cbf25fc');
$User_Agent = 'AdultsmartAfterpayModule/1.0.0 (PHP/7.1.1; Merchant/40351)';
$request_headers = array();
$request_headers[] = 'Authorization: '. 'Basic '.$apikey;
$request_headers[] = 'User-Agent: '. $User_Agent;
$request_headers[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
$ch = curl_init();  
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
//curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt( $ch, CURLOPT_CUSTOMREQUEST,'GET'); 
curl_setopt($ch, CURLOPT_HEADER, 1); 
curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers); 
curl_setopt($ch, CURLOPT_URL, $afterpayURL); 
$result = curl_exec($ch); dump($result); exit; 
    
//----------------------------------------------------------//
    
// call afterpay configuration using GuzzleHttp
$afterpayClient = new \GuzzleHttp\Client(['base_uri' => 'https://api-sandbox.afterpay.com']); 
$apikey = base64_encode('40351'.':'.'5287f73ac23c6199705e0c9310537df1017a5a3e19361069dc2578ced0ab99524adaf94e34e55a64e4ff6c47742a3fcf0c4b4df5e624490a81be65d34cbf25fc');
$response = $afterpayClient->request(Request::METHOD_GET, '/v1/configuration', [ 
		'headers' => [
			'Authorization' => 'Basic '.$apikey,
			'User-Agent' => 'AdultsmartAfterpayModule/1.0.0 (PHP/7.1.1; Merchant/40351)',
			'Accept' => 'application/json' 
		]
	]);
var_dump($response); exit;

// call afterpay configuration using GuzzleHttp
$afterpayClient = new \GuzzleHttp\Client(['base_uri' => 'https://api-sandbox.afterpay.com']); 
$apikey = base64_encode('40351'.':'.'5287f73ac23c6199705e0c9310537df1017a5a3e19361069dc2578ced0ab99524adaf94e34e55a64e4ff6c47742a3fcf0c4b4df5e624490a81be65d34cbf25fc');
$response = $afterpayClient->request(Request::METHOD_GET, '/v1/configuration', [ 
		'headers' => [
			'Authorization' => 'Basic '.$apikey,
        		'User-Agent' => 'AdultsmartAfterpayModule/1.0.0 (PHP/7.1.1; Merchant/40351)',
			'Accept' => 'application/json' 
		]
	]);
$afterpayResponse = json_decode((string)$response->getBody(), true); dump($afterpayResponse); exit;

//----------------------------------
//- page service
//-----------------------------------

    /**
     * @param RouterInterface $router
     * @param array $cart
     * @param array $formCustomer
     * @param array $config
     * @param int $orderId
     * @return string
     * @throws \Exception
     */
    public function getZippayRedirectUrl(RouterInterface $router, array $cart, array $formCustomer, array $config, int $orderId): string 
    {

        $returnUrl = $router->generate('checkout_success_page', [], RouterInterface::ABSOLUTE_URL);
        $cancelUrl = $router->generate('checkout_payment_page', [], RouterInterface::ABSOLUTE_URL);
        $currencyCode = 'AUD';

	//------------------------------
	//shopper address
	//------------------------------	
	$cAddData = array( "line1"=>$formCustomer['address1'], "city"=>$formCustomer['city'], "state"=>$formCustomer['state'], 
			   "postal_code"=>$formCustomer['zip'], "country"=>$formCustomer['country'], 'first_name'=>$formCustomer['first_name'],
			   'last_name'=>$formCustomer['last_name'] );
	$shopperAddress = new \zipMoney\Model\Address($cAddData); 

	//------------------------------
	//shopper statistics
	//------------------------------
	$cStatData = array( "account_created"=>"2015-09-09T19:58:47.697Z", "sales_total_number"=>2, "sales_total_amount"=>450, 
   			    "sales_avg_value"=>250, "sales_max_value"=>350, "refunds_total_amount"=>0, "previous_chargeback"=>false,
   			    "currency"=>$currencyCode );
	$shopperStats = new \zipMoney\Model\ShopperStatistics($cStatData);

	//------------------------------
	//shopper
	//------------------------------
	$shopperData = array( 'title'=>'MR/MS', 'first_name'=>$formCustomer['first_name'], 'last_name'=>$formCustomer['last_name'], 
			'middle_name'=>'', 'phone'=>'0400000000', 'email'=>$formCustomer['email'], 'birth_date'=>"1900-01-01", 'gender'=>'Male', 
   			'statistics'=>$shopperStats, 'billing_address'=>$shopperAddress );
	$shopperDetails = new \zipMoney\Model\Shopper($shopperData);

	//-------------------------------
	//order shipping details
	//-------------------------------
	$shipTrackData = array('uri'=>"http://tracking.com?code=CBX-343", "number"=>"CBX-343", "carrier"=>"tracking.com");
	//$shipTrackData = array('uri'=>"", "number"=>"", "carrier"=>"");
	$shipTrackDetails = new \zipMoney\Model\OrderShippingTracking($shipTrackData); 
	$shipAddressDetails = $shopperAddress;
	$shippingDetails = new \zipMoney\Model\OrderShipping(
				array("pickup"=>false,"tracking"=>$shipTrackDetails,"address"=>$shipAddressDetails)); 

	//-------------------------------
	//order items & $order
	//-------------------------------
	/*
         * iterate though each item and add to an item details
         */
	$oItems = array(); $itemTotalValue = 0.0; //dump($cart);
	$shippingValue = $cart['shipping'];
        foreach ($cart['products'] as $item) { 
          $product = $this->productRepository->find($item['product_id']);
          $productImage = $product->getProductsImage(); 
	  $pImgUrl = "http://xsales.com.au/images/".$productImage;
          $set_pprice = $product->getFinalPrice(); //echo $set_price;
          $sp_price = $product->getSpecial()->getFinalPrice(); //echo $sp_price;
          $set_price = 0;  //$set_price = $set_pprice;
          if($sp_price > 0){ $set_price = $sp_price; } else { $set_price = $set_pprice; }
            
	  //$itemAmount = new BasicAmountType($currencyCode, $set_price ); 
          $itemTotalValue += $set_price * $item['quantity'];

          //$taxTotalValue += 0; $itemDetails = new PaymentDetailsItemType();
          //$itemDetails->Name = $product->getProductsDescription()->getProductsName();
          //$itemDetails->Amount = $itemAmount;
          //$itemDetails->Quantity = $item['quantity'];
          //$itemDetails->ItemCategory = 'Physical';
          //$itemDetails->Number = $item['product_id'];
          //$itemDetails->Tax = new BasicAmountType($currencyCode, 0);

	  $anItem = array("name"=>$product->getProductsDescription()->getProductsName(), "amount"=>$set_price, 
		     		"quantity"=>$item['quantity'], "type"=>"sku", "reference"=>$item['product_id'], 
				"image_uri"=> $pImgUrl ); 
	  $itemDetails = new \zipMoney\Model\OrderItem($anItem);
	  array_push($oItems, $itemDetails);
        }

	//add shipping price to the order
	$anItem = array("name"=>"Shipping and Handling", "amount"=>$cart['shipping'], "quantity"=>1, "type"=>"shipping"); 
	$itemDetails = new \zipMoney\Model\OrderItem($anItem); array_push($oItems, $itemDetails);
	$itemTotalValue += $cart['shipping'];
	$itemValue = number_format($itemTotalValue, 2); 

	$orderDetails = new \zipMoney\Model\CheckoutOrder(
				array("reference"=>$orderId, "amount"=>$itemValue, "currency"=>$currencyCode, 
					"shipping"=>$shippingDetails, "items"=>$oItems ) ); //dump($orderDetails); exit; 

	//-------------------------------
	//create checkout via api
	//-------------------------------
	$configDetails = new \zipMoney\Model\CheckoutConfiguration( array('redirect_uri'=> $returnUrl ) ); 
	$requestParams = array('shopper'=>$shopperDetails, 'order'=>$orderDetails, 'config'=> $configDetails); 

	// Configure API key authorization: Authorization
	//\zipMoney\Configuration::getDefaultConfiguration()->setApiKey('Authorization', 'yhd7bxotuh/EHxKuJp9yH09Rn/8Hbz1EcAmT9S62P9U=');
	\zipMoney\Configuration::getDefaultConfiguration()->setApiKey('Authorization', 'd1bdEnS8ocFPsW4foatlq0HgKAlqr520R1F6v2yYkCU=');
	\zipMoney\Configuration::getDefaultConfiguration()->setApiKeyPrefix('Authorization', 'Bearer');
	//\zipMoney\Configuration::getDefaultConfiguration()->setEnvironment('sandbox'); // Allowed values are  ( sandbox | production )
	\zipMoney\Configuration::getDefaultConfiguration()->setEnvironment('production'); // Allowed values are  ( sandbox | production )
	//\zipMoney\Configuration::getDefaultConfiguration()->setPlatform('Php/5.6'); // E.g. Magento/1.9.1.2
	
	$api_instance = new \zipMoney\Api\CheckoutsApi();
	$request = new \zipMoney\Model\CreateCheckoutRequest($requestParams); 
	//dump($request); exit; 
        //$requestjson = $request->__toString(); dump($requestjson); exit;
	try {
		$result = $api_instance->checkoutsCreate($request);
    		//$result = $api_instance->checkoutsCreate($requestjson);  
    		//echo '<pre>'; dump($result); echo '</pre>'; exit;
		$resultURI = $result->getUri(); 
		return $resultURI;

	} catch (Exception $e) {
    		echo 'Exception when calling CheckoutsApi->checkoutsCreate: ', $e->getMessage(), PHP_EOL; 
		$result = '';  return $result;	
	}

    }


    /**
     * @param RouterInterface $router
     * @param array $cart
     * @param array $formCustomer
     * @param array $config
     * @param int $orderId
     * @return string
     * @throws \Exception
     */
    public function getAfterPayOrderToken(RouterInterface $router, array $cart, array $formCustomer, array $config, int $orderId): string 
    {

        $returnUrl = $router->generate('checkout_success_page', [], RouterInterface::ABSOLUTE_URL);
        //$cancelUrl = $router->generate('checkout_payment_page', [], RouterInterface::ABSOLUTE_URL);
		$cancelUrl = $router->generate('cart_page', [], RouterInterface::ABSOLUTE_URL);
        $currencyCode = 'AUD';


		//------------------------------
		//consumer
		//------------------------------	
		$consumer = array('phoneNumber'=>'0400000000', 'givenNames'=>$formCustomer['first_name'], 'surname'=>$formCustomer['last_name'], 
						  'email'=>$formCustomer['email'] );

		//-------------------------------
		// billing & shipping
		//-------------------------------
		$billing = array( "name" => $formCustomer['first_name'].' '.$formCustomer['last_name'], "line1"=>$formCustomer['address1'], 
						  "line2"=>$formCustomer['address2'], "suburb"=>$formCustomer['city'], "state"=>$formCustomer['state'], 
						  "postcode"=>$formCustomer['zip'], "countrycode"=>$formCustomer['country'], 'phoneNumber'=>'0400000000' );
		$shipping = $billing; 

		//-------------------------------
		//order items & $order
		//-------------------------------
		$oItems = array(); $itemTotalValue = 0.0; //dump($cart);
		$shippingValue = $cart['shipping'];
    	foreach ($cart['products'] as $item) { 
			$product = $this->productRepository->find($item['product_id']);
    	    $productImage = $product->getProductsImage(); 
			$pImgUrl = "http://xsales.com.au/images/".$productImage;
    	    $set_pprice = $product->getFinalPrice(); //echo $set_price;
    	    $sp_price = $product->getSpecial()->getFinalPrice(); //echo $sp_price;
    	    $set_price = 0;  //$set_price = $set_pprice;
    	    if($sp_price > 0){ $set_price = $sp_price; } else { $set_price = $set_pprice; }
            //$itemAmount = new BasicAmountType($currencyCode, $set_price ); 
    	    $itemTotalValue += $set_price * $item['quantity'];

    	    //$taxTotalValue += 0; $itemDetails = new PaymentDetailsItemType();
    	    //$itemDetails->Name = $product->getProductsDescription()->getProductsName();
    	    //$itemDetails->Amount = $itemAmount;
    	    //$itemDetails->Quantity = $item['quantity'];
    	    //$itemDetails->ItemCategory = 'Physical';
    	    //$itemDetails->Number = $item['product_id'];
    	    //$itemDetails->Tax = new BasicAmountType($currencyCode, 0);
		  	$anItem = array("name"=>$product->getProductsDescription()->getProductsName(), 
							"sku"=>$item['product_id'], "quantity"=>$item['quantity'], 
							"price" => array("amount"=>number_format($set_price, 2, '.', ''), 'currency'=> $currencyCode ) );
	  		array_push($oItems, $anItem);
        }		
		$itemTotalValue += $cart['shipping']; $itemValue = number_format($itemTotalValue, 2, '.', ''); 
		$shippingAmount = number_format($cart['shipping'], 2);

		//-----------------------------------------------
		//create checkout via api -- /v1/orders endpoint		//-----------------------------------------------
		$afterpayClient = new \GuzzleHttp\Client(['base_uri' => 'https://api-sandbox.afterpay.com']); 
		$apikey = base64_encode('40351'.':'.'5287f73ac23c6199705e0c9310537df1017a5a3e19361069dc2578ced0ab99524adaf94e34e55a64e4ff6c47742a3fcf0c4b4df5e624490a81be65d34cbf25fc');
		$response = $afterpayClient->request(\Symfony\Component\HttpFoundation\Request::METHOD_POST, '/v1/orders', [ 
						'headers' => [
        					'Authorization' => 'Basic '.$apikey,
							'Content-Type' => 'application/json', 
        					'User-Agent' => 'AdultsmartAfterpayModule/1.0.0 (PHP/7.1.1; Merchant/40351)',
							'Accept' => 'application/json'  ],
						'json' => [
							'totalAmount' => array("amount"=>$itemValue, "currency"=>$currencyCode),
							'consumer' => $consumer,
							'billing' => $billing, 
							'shipping' => $shipping, 
							'items' => $oItems, 
							'merchant'=>[ 'redirectConfirmUrl' => $returnUrl, 'redirectCancelUrl' => $cancelUrl ],
							'paymentType' => 'PAY_BY_INSTALLMENT', 
							'merchantReference' => $orderId, 
							'shippingAmount' => array('amount'=>$shippingAmount, 'currency'=>'AUD') ],  
					]);
		// response for /v1/configuration 
		$afterpayResponse = json_decode((string)$response->getBody(), true); $enableAfterPay = 0; 
		dump($afterpayResponse); exit; 

		//dump($cartInfo['subtotal'] + $cartInfo['shipping']);
		if($afterpayResponse[0]['maximumAmount']['amount'] > 0){
  			if ( ( $cartInfo['subtotal'] + $cartInfo['shipping'] ) < floatval($afterpayResponse[0]['maximumAmount']['amount']) ){ 
				$enableAfterPay = 1; 
			}
		} //dump($enableAfterPay); exit;
		
    }









