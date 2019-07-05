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
