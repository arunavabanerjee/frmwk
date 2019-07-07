<?php

	class Order {
		
		private $f_name = '';
		private $l_name = '';
		private $email = '';
		private $phone = '';
		private $address = '';
		private $city = '';
		private $state = '';
		private $postcode = '';
		private $shipping_f_name = '';
		private $shipping_l_name = '';
		private $shipping_phone = '';
		private $shipping_address = '';
		private $shipping_city = '';
		private $shipping_state = '';
		private $shipping_postcode = '';
		private $payment_method = '';
		private $payment_method_text = '';
		private $cc_number = '';
		private $cc_name = '';
		private $cc_month = '';
		private $cc_year = '';
		private $cc_code = '';
		private $xsales_order = array();
		private $xsales_order_id;
		private $error_msg_txt;
		
		public function Order() {
			
		}	
		
		public function getXsalesOrder() {
			return $this->xsales_order;
		}
		
		public function getXsalesOrderId() {
			return $this->xsales_order_id;
		}
		
		public function setPostData($data) {
			$this->f_name = $data['f_name'];
			$this->l_name = $data['l_name'];
			$this->email = $data['email'];
			$this->phone = $data['phone'];
			$this->address = $data['address'];
			$this->city = $data['city'];
			$this->state = $data['state'];
			$this->postcode = $data['postcode'];
			$this->country = $data['country'];
			if ($data['delivery_as_billing'] == 'true') {
				$this->shipping_f_name = $data['f_name'];
				$this->shipping_l_name = $data['l_name'];
				$this->shipping_phone = $data['phone'];
				$this->shipping_address = $data['address'];
				$this->shipping_city = $data['city'];
				$this->shipping_state = $data['state'];
				$this->shipping_postcode = $data['postcode'];
				$this->shipping_country = $data['country'];
			}
			else {
				$this->shipping_f_name = $data['shipping_f_name'];
				$this->shipping_l_name = $data['shipping_l_name'];
				$this->shipping_phone = $data['shipping_phone'];
				$this->shipping_address = $data['shipping_address'];
				$this->shipping_city = $data['shipping_city'];
				$this->shipping_state = $data['shipping_state'];
				$this->shipping_postcode = $data['shipping_postcode'];
				$this->shipping_country = $data['shipping_country'];
			}
				
			if ($data['cc_checked'] == 'true') {
				$this->payment_method = 'credit_card';
				$this->payment_method_text = 'Credit/Debit card via PayPal';
				$this->cc_number = $data['cc_number'];
				$this->cc_name = $data['cc_name'];
				$this->cc_code = $data['cc_code'];
				$this->cc_month = $data['cc_month'];
				$this->cc_year = $data['cc_year'];
			}
			elseif ($data['cheque'] == 'true') {
				$this->payment_method = 'cheque';
				$this->payment_method_text = 'Check/Money Order';
			}
			elseif ($data['bank'] == 'true') {
				$this->payment_method = 'bank_transfer';
				$this->payment_method_text = 'Bank/POST transfer prepayment';
			}
			elseif ($data['paypal'] == 'true') {
				$this->payment_method = 'paypal';
				$this->payment_method_text = 'PayPal (including Credit and Debit Cards)';
			}
			elseif ($data['zippay'] == 'true') {
				$this->payment_method = 'zippay';
				$this->payment_method_text = 'Zippay';
			}

			
		}
		
		public function process() {
			if ($this->xsales_order['internal']['payment_method'] == 'cheque' || $this->xsales_order['internal']['payment_method'] == 'bank_transfer') {
				$this->saveXsalesOrder();
				$this->sendEmail();				
				return 'success';
			}
			elseif ($this->xsales_order['internal']['payment_method'] == 'credit_card') {
				if ($this->processPayPalCCPayment()) {
					$this->saveXsalesOrder();
					$this->sendEmail();
					return 'success';
				}
				else {
					return 'failure';
				}
			}
			elseif ($this->xsales_order['internal']['payment_method'] == 'paypal') {
				$this->saveXsalesOrder();
				$this->sendEmail();		
				return 'get_paypal_form';
			}
			elseif ($this->xsales_order['internal']['payment_method'] == 'zippay') { 
				$this->saveXsalesOrder();
				$this->sendEmail();
				$zipcheckoutUrl = $this->getZippayCheckoutUrl();
				return $zipcheckoutUrl; 
			}
		}
		
		private function sendEmail() {
			$mail_contents = file_get_contents('layout/mail.html');
			$mail_contents = str_replace('{-name-}', $this->xsales_order['internal']['f_name'] . ' ' . $this->xsales_order['internal']['l_name'], $mail_contents);
			$mail_contents = str_replace('{-subtotal_cost-}', Utils::getPriceNumberFormatAndCurrency($this->xsales_order['internal']['cart_subtotal']), $mail_contents);
			$mail_contents = str_replace('{-shipping_cost-}', Utils::getPriceNumberFormatAndCurrency($this->xsales_order['internal']['shipping']), $mail_contents);
			$mail_contents = str_replace('{-total_cost-}', Utils::getPriceNumberFormatAndCurrency($this->xsales_order['internal']['shipping'] + $this->xsales_order['internal']['cart_subtotal']), $mail_contents);
			
			$payment_details_text = '';
			if ($this->xsales_order['internal']['payment_method'] == 'bank_transfer') {
				$payment_details_text = '<p>Bank Transfer</p>
<p>Please use the following details to transfer your total order value:</p><br />
								  
  	Account Name: RMV Management Pty Ltd<br />
  	Bank Name: National Australia Bank<br />
  	BSB (IBAN): 082-057 <br />
  	Acct No: 19-114-0136 <br />
  	SWIFT:  NATAAU3303M<br />
	Address:  P.O. Box 474<br />
	Riverwood   2210 NSW<br /><br />
	
<p>Your order will not ship until we receive payments in the above account.</p>						
						';
			}
			elseif ($this->xsales_order['internal']['payment_method'] == 'cheque') {
				$payment_details_text = 'Payment by Cheque / Money Order. <br />
						Make Payable to: <br />
						RMV Management Pty Ltd PO Box 474 Riverwood NSW 2210 Australia';
			}
			elseif ($this->xsales_order['internal']['payment_method'] == 'credit_card') {
				$payment_details_text = 'Payment done by credit/debit card.';
			}
			elseif ($this->xsales_order['internal']['payment_method'] == 'paypal') {
				$payment_details_text = 'Payment done by PayPal.';
			}
			elseif ($this->xsales_order['internal']['payment_method'] == 'zippay') { 
				$payment_details_text = 'Payment done by Zippay.';
			}
			$mail_contents = str_replace('{-payment_details-}', $payment_details_text, $mail_contents);
			
			$dvd_list = '';
			foreach ($this->xsales_order['orders_products'] as $ordered_product) {
				$dvd_list .= '<li>' . $ordered_product['products_quantity'] . ' X ' . $ordered_product['products_name'];
				if (!empty($ordered_product['attributes'])) {
					foreach ($ordered_product['attributes'] as $attrib) {
						$dvd_list .= '<br />' . $attrib['products_options'] . ': ' . $attrib['products_options_values'];
					}
				}
				$dvd_list .= '</li>' . "\n";
			}
			$mail_contents = str_replace('{-prod_list-}', $dvd_list, $mail_contents);
			
			$mail_contents = str_replace('{-shipping_address-}', $this->xsales_order['internal']['full_delivery_address'], $mail_contents);
			
			$notice_text = 'This is a multi-part message in MIME format.';
			$txt_email_contents = strip_tags($mail_contents);
			$semi_rand = md5(time());
			$mime_boundary = "==MULTIPART_BOUNDARY_$semi_rand";
			$mime_boundary_header = chr(34) . $mime_boundary . chr(34);
			
			$to = $this->xsales_order['internal']['f_name'] . ' ' . $this->xsales_order['internal']['f_name'] . 
				'<' . $this->xsales_order['orders']['customers_email_address'] . '>';
			$from = 'Penis Plugs EU <info@penisplugs.eu>';
			$subject = 'Your order at the Penis Plugs EU';
			
			$body = "\r\n" . 
				"--" . $mime_boundary . "\r\n" .
				"Content-Type: text/html; charset=us-ascii" . "\r\n" .
				"Content-Transfer-Encoding: 7bit" . "\r\n" . "\r\n" .				
				$mail_contents . "\r\n" . "\r\n" .				
				"--" . $mime_boundary . "\r\n";
			
			$headers = "From: " . $from . "\n" .
				"Reply-to: " . $from . "\n" .
				"MIME-Version: 1.0\n" .
				"Content-Type: multipart/mixed;\n" .
				' boundary="' . $mime_boundary . '"';
			
			mail(
				$to, 
				$subject, 
				$body,
				$headers
			);
			
			mail(
				'Penis Plugs EU <info@penisplugs.eu>',
				$subject,
				$body,
				$headers
			);
			
			return true;
		}

        private function getCCType($num) {
            if (preg_match('/^4[0-9]{6,}$/', $num)) {
                return 'Visa';
            }
            elseif (preg_match('/^6(?:011|5[0-9]{2})[0-9]{3,}$/', $num)) {
                return 'Discover';
            }
            elseif (preg_match('/^3[47][0-9]{5,}$/', $num)) {
                return 'Amex';
            }
            elseif (preg_match('/^5[1-5][0-9]{5,}|222[1-9][0-9]{3,}|22[3-9][0-9]{4,}|2[3-6][0-9]{5,}|27[01][0-9]{4,}|2720[0-9]{3,}$/', $num)) {
                return 'MasterCard';
            }

            return 'Visa';
        }

        public function processPayPalCCPayment()
        {
            require_once(LOCAL_PATH.'/vendor/autoload.php');

            $address = new \PayPal\EBLBaseComponents\AddressType();
            $address->Name = $this->xsales_order['internal']['f_name']." ".$this->xsales_order['internal']['l_name'];
            $address->Street1 = $this->xsales_order['orders']['customers_street_address'];
            $address->Street2 = '';
            $address->CityName = $this->xsales_order['orders']['customers_city'];
            $address->StateOrProvince = $this->xsales_order['orders']['customers_state'];
            $address->PostalCode = $this->xsales_order['orders']['customers_postcode'];
            $country_code = Utils::getCountryDetailsForName($this->xsales_order['orders']['customers_country']);
            $address->Country = $country_code['countries_iso_code_2'];
            $address->Phone = $this->xsales_order['orders']['customers_telephone'];

            $paymentDetails = new \PayPal\EBLBaseComponents\PaymentDetailsType();
            $paymentDetails->ShipToAddress = $address;

            $total = $this->xsales_order['internal']['cart_subtotal'] + $this->xsales_order['internal']['shipping'];
            //$paymentDetails->OrderTotal = new \PayPal\CoreComponentTypes\BasicAmountType('AUD', $total);
            $paymentDetails->OrderTotal = new \PayPal\CoreComponentTypes\BasicAmountType('EUR', $total);

            $personName = new \PayPal\EBLBaseComponents\PersonNameType();
            $personName->FirstName = $this->xsales_order['internal']['f_name'];
            $personName->LastName = $this->xsales_order['internal']['l_name'];
            //information about the payer
            $payer = new \PayPal\EBLBaseComponents\PayerInfoType();
            $payer->PayerName = $personName;
            $payer->Address = $address;
            $payer->PayerCountry = $country_code['countries_iso_code_2'];
            $cardDetails = new \PayPal\EBLBaseComponents\CreditCardDetailsType();
            $cardDetails->CreditCardNumber = $this->xsales_order['internal']['cc_number'];
            $cardDetails->CreditCardType = $this->getCCType($this->xsales_order['internal']['cc_number']);

            $cardDetails->ExpMonth = $this->xsales_order['internal']['cc_month'];
            $cardDetails->ExpYear = $this->xsales_order['internal']['cc_year'];
            $cardDetails->CVV2 = $this->xsales_order['internal']['cc_code'];
            $cardDetails->CardOwner = $payer;

            $ddReqDetails = new \PayPal\EBLBaseComponents\DoDirectPaymentRequestDetailsType();
            $ddReqDetails->CreditCard = $cardDetails;
            $ddReqDetails->PaymentDetails = $paymentDetails;
            $ddReqDetails->PaymentAction = 'Sale';
            $doDirectPaymentReq = new \PayPal\PayPalAPI\DoDirectPaymentReq();
            $doDirectPaymentReq->DoDirectPaymentRequest = new \PayPal\PayPalAPI\DoDirectPaymentRequestType($ddReqDetails);

            $paypalService = new \PayPal\Service\PayPalAPIInterfaceServiceService([
                // Signature Credential
                "acct1.UserName" => "maria.xsales_api1.gmail.com",
                "acct1.Password" => "RYXUCC54TSHWGTQG",
                "acct1.Signature" => "A.-XtvgJ925I16amYbZRWV8obgK9AAqFbbNXOSGc1YtlSV0jKJI04a8T",
                // Subject is optional and is required only in case of third party authorization
                // "acct1.Subject" => "",

                // Sample Certificate Credential
                //"acct1.UserName" => "valeriu-facilitator_api1.buzila.ro",
                //"acct1.Password" => "5B84RHWRNHBT3YUE",
                //"acct1.Signature" => "An5ns1Kso7MWUdW4ErQKJJJ4qi4-A0SDrt6ydcbgZnfTz46ItvwagY1H",
                // Certificate path relative to config folder or absolute path in file system
                // "acct1.CertPath" => "cert_key.pem",
                // Subject is optional and is required only in case of third party authorization
                // "acct1.Subject" => "",

                "mode" => "live",
                //"mode" => "tls",
                //"mode" => "sandbox",
            ]);

            try {
                /* wrap API method calls on the service object with a try catch */
                $doDirectPaymentResponse = $paypalService->DoDirectPayment($doDirectPaymentReq);
            }
            catch (Exception $ex) {
                $this->setErrorMsgTxt($ex->getMessage());
                return false;
            }

            if (isset($doDirectPaymentResponse)) {
                if ($doDirectPaymentResponse->Ack == 'Success' || $doDirectPaymentResponse->Ack == 'SuccessWithWarning') {
                    return true;
                }

                $errorMsg = [];
                foreach ($doDirectPaymentResponse->Errors as $error) {
                    $errorMsg[] = $error->ShortMessage.': '.$error->LongMessage;
                }
                $this->setErrorMsgTxt(implode('; ', $errorMsg));

                return false;
            }

            return false;
        }
		
		private function processCCPayment() {
			include_once('classes/eway.php' );
			
			$live_transaction = true;
			$eway_id = '17167462';
			$eway_payment_method = 'REAL_TIME_CVN';
			
			$ewayClient = new EwayPaymentLive($eway_id, $eway_payment_method, $live_transaction);
			$total = $this->xsales_order['internal']['cart_subtotal'] + $this->xsales_order['internal']['shipping']; 
			$ewayClient->setTransactionData('TotalAmount', round($total * 100)); // we need to send the total amount in cents
			$ewayClient->setTransactionData('CustomerFirstName', Utils::convert_special_chars($this->xsales_order['internal']['f_name']));
			$ewayClient->setTransactionData('CustomerLastName', Utils::convert_special_chars($this->xsales_order['internal']['f_name']));
			$ewayClient->setTransactionData('CustomerEmail', $this->xsales_order['orders']['customers_email_address']);
			$address = $this->xsales_order['orders']['customers_street_address'] . ', ' . 
				$this->xsales_order['orders']['customers_city'] . ', ' . 
				$this->xsales_order['orders']['customers_state'] . ', ' .
				$this->xsales_order['orders']['customers_country'];
			
			$ewayClient->setTransactionData('CustomerAddress', $address);
			$ewayClient->setTransactionData('CustomerPostcode', $this->xsales_order['orders']['customers_postcode']);
			$ewayClient->setTransactionData('CustomerInvoiceDescription', '');
			$ewayClient->setTransactionData('CustomerInvoiceRef', '');
			$ewayClient->setTransactionData('CardHoldersName', $this->xsales_order['internal']['cc_name']);
			$ewayClient->setTransactionData('CardNumber', $this->xsales_order['internal']['cc_number']);
			$ewayClient->setTransactionData('CardExpiryMonth', $this->xsales_order['internal']['cc_month']);
			$ewayClient->setTransactionData('CardExpiryYear', $this->xsales_order['internal']['cc_year']);
			$ewayClient->setTransactionData('CVN', $this->xsales_order['internal']['cc_code']);
			$ewayClient->setTransactionData('TrxnNumber', '');
			$ewayClient->setTransactionData('Option1', '');
			$ewayClient->setTransactionData('Option2', '');
			$ewayClient->setTransactionData('Option3', '');
				
			$ewayClient->setTransactionData('CustomerIPAddress', $ewayClient->getVisitorIP()); //mandatory field when using Geo-IP Anti-Fraud
			$ewayClient->setTransactionData('CustomerBillingCountry', $this->xsales_order['internal']['country_code']); //mandatory field when using Geo-IP Anti-Fraud
				
			$ewayResponseFields = $ewayClient->doPayment();
			
			if ($ewayResponseFields['EWAYTRXNSTATUS'] == 'False') {
				$this->setErrorMsgTxt($ewayResponseFields['EWAYTRXNERROR']);
				return false;
			}
			
			return true;
		}
		
		public function setErrorMsgTxt($txt) {
			$this->error_msg_txt = $txt;
		}
		
		public function getErrorMsgTxt() {
			return $this->error_msg_txt;	
		}
		
		private function saveXsalesCustomer() {
			global $db_conn;
				
			$sql_check = sprintf('SELECT customers_id FROM customers_retail WHERE customers_email_address = "%s" LIMIT 1', mysql_real_escape_string($this->xsales_order['orders']['customers_email_address'], $db_conn));
			$cust_id = DB::getRow($sql_check);
			if (isset($cust_id['customers_id'])) {
				return $cust_id['customers_id'];
			}
				
			$sql = sprintf(
					'INSERT INTO customers_retail
				(
					customers_firstname,
					customers_lastname,
					customers_email_address,
					customers_telephone
				)
				VALUES
				(
					"%s",
					"%s",
					"%s",
					"%s"
				)',
					mysql_real_escape_string($this->xsales_order['internal']['f_name'], $db_conn),
					mysql_real_escape_string($this->xsales_order['internal']['l_name'], $db_conn),
					mysql_real_escape_string($this->xsales_order['orders']['customers_email_address'], $db_conn),
					mysql_real_escape_string($this->xsales_order['orders']['customers_telephone'], $db_conn)
			);
			DB::run($sql);
			$cust_id = DB::getLastId();
			
			$country_details = Utils::getCountryDetailsForName($this->xsales_order['orders']['billing_country']);
				
			$sql = sprintf(
					'INSERT INTO address_book_retail
				(
					customers_id,
					entry_firstname,
					entry_lastname,
					entry_street_address,
					entry_postcode,
					entry_city,
					entry_state,
					entry_country_id
				)
				VALUES
				(
					%d,
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					%d
				)',
					$cust_id,
					mysql_real_escape_string($this->xsales_order['internal']['f_name'], $db_conn),
					mysql_real_escape_string($this->xsales_order['internal']['l_name'], $db_conn),
					mysql_real_escape_string($this->xsales_order['orders']['billing_street_address'], $db_conn),
					mysql_real_escape_string($this->xsales_order['orders']['billing_postcode'], $db_conn),
					mysql_real_escape_string($this->xsales_order['orders']['billing_city'], $db_conn),
					mysql_real_escape_string($this->xsales_order['orders']['billing_state'], $db_conn),
					$country_details['countries_id']
			);
			DB::run($sql);
			$customers_default_address_id = DB::getLastId();
				
			$sql = sprintf('UPDATE customers_retail SET customers_default_address_id = %d WHERE customers_id = %d', $customers_default_address_id, $cust_id);
			DB::run($sql);
				
			return $cust_id;
		}
		
		private function saveXsalesOrder() {
			global $db_conn;
			
			$this->xsales_order['orders']['customers_id'] = $this->saveXsalesCustomer();
			
			$sql = sprintf('INSERT INTO orders_retail 
				(
					customers_id, 
					customers_name, 
					customers_street_address, 
					customers_city, 
					customers_postcode, 
					customers_state,
					customers_country, 
					customers_telephone, 
					customers_email_address,
					customers_address_format_id,
					delivery_name,
					delivery_street_address,
					delivery_city,
					delivery_postcode,
					delivery_state,
					delivery_country,
					delivery_address_format_id,
					billing_name,
					billing_street_address,
					billing_city,
					billing_postcode,
					billing_state,
					billing_country,
					billing_address_format_id,
					payment_method,
					date_purchased,
					orders_status,
					currency,
					currency_value
				)
					VALUES 
				(
					%d,
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					"%s",
					%s,
					"%s",
					"%s",
					"%s"					
				)',
				$this->xsales_order['orders']['customers_id'],
				mysql_real_escape_string($this->xsales_order['orders']['customers_name'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['customers_street_address'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['customers_city'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['customers_postcode'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['customers_state'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['customers_country'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['customers_telephone'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['customers_email_address'], $db_conn),
				1,
				mysql_real_escape_string($this->xsales_order['orders']['delivery_name'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['delivery_street_address'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['delivery_city'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['delivery_postcode'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['delivery_state'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['delivery_country'], $db_conn),
				1,
				mysql_real_escape_string($this->xsales_order['orders']['billing_name'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['billing_street_address'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['billing_city'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['billing_postcode'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['billing_state'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['billing_country'], $db_conn),
				1,
				mysql_real_escape_string($this->xsales_order['orders']['payment_method'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['date_purchased'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['orders_status'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['currency'], $db_conn),
				mysql_real_escape_string($this->xsales_order['orders']['currency_value'], $db_conn)
			);
			
			DB::run($sql);
			$this->xsales_order_id = DB::getLastId();
			
			foreach ($this->xsales_order['orders_products'] as $ordered_product) {
				$sql = sprintf('
					INSERT INTO orders_products_retail (
						orders_id, 
						products_id, 
						products_model, 
						products_name, 
						products_price, 
						final_price, 
						products_tax, 
						products_quantity
					)
					VALUES (
						%d,
						%d,
						"%s",
						"%s",
						"%s",
						"%s",
						"%s",
						%d
					)',
					$this->xsales_order_id,
					$ordered_product['products_id'],
					$ordered_product['products_model'],
					mysql_real_escape_string($ordered_product['products_name'], $db_conn),
					$ordered_product['products_price'],
					$ordered_product['final_price'],
					$ordered_product['products_tax'],
					$ordered_product['products_quantity']
				);
				DB::run($sql);
				$orders_product_id = DB::getLastId();
				
				$sql = sprintf(
						'UPDATE products SET products_quantity = products_quantity - %d WHERE products_id = %d',
						(int)$ordered_product['products_quantity'],
						(int)$ordered_product['products_id']
				);
				DB::run($sql);
				
				if (!empty($ordered_product['attributes'])) {
					foreach ($ordered_product['attributes'] as $attrib) {
						$sql = sprintf('
							INSERT INTO orders_products_attributes_retail
								(orders_id, orders_products_id, products_options, products_options_values, options_values_price, price_prefix, options_sku)
							VALUES (
								%d,
								%d,
								"%s",
								"%s",
								"%s",
								"%s",
								"%s"
							)
							',
							$this->xsales_order_id,
							$orders_product_id,
							$attrib['products_options'],
							$attrib['products_options_values'],
							$attrib['options_values_price'],
							$attrib['price_prefix'],
							$attrib['options_sku']
						);
						DB::run($sql);
						
						$sql = sprintf(
								'UPDATE products_attributes SET quantity = quantity - %d WHERE options_sku = "%s"',
								(int)$ordered_product['products_quantity'],
								$attrib['options_sku']
						);
						DB::run($sql);
					}
				}
			}
			
			$sql = sprintf('
				INSERT INTO orders_status_history_retail (orders_id, orders_status_id, date_added, customer_notified, comments)
					VALUES (
						%d,
						%d,
						%s,
						%d,
						"%s"
					)',
				$this->xsales_order_id,
				$this->xsales_order['orders_status_history']['orders_status_id'],
				$this->xsales_order['orders_status_history']['date_added'],
				1,
				$this->xsales_order['orders_status_history']['comments']
			);
			DB::run($sql);
			
			foreach ($this->xsales_order['orders_total'] as $ot) {
				$sql = sprintf(
					'INSERT INTO orders_total_retail (orders_id, title, text, value, class, sort_order)
						VALUES (%d, "%s", "%s", "%s", "%s", %d)',
					$this->xsales_order_id,
					$ot['title'],
					$ot['text'],
					$ot['value'],
					$ot['class'],
					$ot['sort_order']
				);
				DB::run($sql);
			}
			
			return true;			
		}
		
		public function updateOrderStatus($id, $status) {
			global $db_conn;
			$sql = sprintf(
					'UPDATE orders_retail SET orders_status = %d
						WHERE orders_id = %d',
					$status,
					$id
			);
			DB::run($sql);
		}
		
		public function updateOrderComment($id, $status, $comment) {
			global $db_conn;
			$sql = sprintf('
				INSERT INTO orders_status_history_retail (orders_id, orders_status_id, date_added, customer_notified, comments)
					VALUES (
						%d,
						%d,
						NOW(),
						%d,
						"%s"
					)',
				$id,
				$status,
				0,
				mysql_real_escape_string($comment, $db_conn)
			);
			DB::run($sql);
		}
		
		private function convertToXsalesOrder() {
			// setting 99999999			
			$this->xsales_order['orders']['customers_id'] = 99999999;
			$this->xsales_order['orders']['customers_name'] = $this->f_name . ' ' . $this->l_name;
			$this->xsales_order['orders']['customers_street_address'] = $this->address;
			$this->xsales_order['orders']['customers_city'] = $this->city;
			$this->xsales_order['orders']['customers_postcode'] = $this->postcode;
			$this->xsales_order['orders']['customers_state'] = $this->state;

			$this->xsales_order['orders']['customers_country'] = $this->country;
			$country_details = Utils::getCountryDetailsForName($this->country);
			$this->xsales_order['internal']['country_code'] = $country_details['countries_iso_code_2'];
			
			$this->xsales_order['orders']['customers_telephone'] = $this->phone;
			$this->xsales_order['orders']['customers_email_address'] = $this->email;
			$this->xsales_order['orders']['delivery_name'] = $this->shipping_f_name . ' ' . $this->shipping_l_name;
			$this->xsales_order['orders']['delivery_street_address'] = $this->shipping_address;
			$this->xsales_order['orders']['delivery_city'] = $this->shipping_city;
			$this->xsales_order['orders']['delivery_postcode'] = $this->shipping_postcode;
			$this->xsales_order['orders']['delivery_state'] = $this->shipping_state;
			
			$this->xsales_order['orders']['delivery_country'] = $this->shipping_country;
			$this->xsales_order['internal']['full_delivery_address'] = $this->xsales_order['orders']['delivery_name'] . ', ' .
					$this->xsales_order['orders']['delivery_street_address'] . ', ' .
					$this->xsales_order['orders']['delivery_city'] . ', ' .
					$this->xsales_order['orders']['delivery_postcode'] . ', ' .
					$this->xsales_order['orders']['delivery_state'] . ', ' .
					$this->xsales_order['orders']['delivery_country'];
			
			$this->xsales_order['orders']['billing_name'] = $this->f_name . ' ' . $this->l_name;
			$this->xsales_order['orders']['billing_street_address'] = $this->address;
			$this->xsales_order['orders']['billing_city'] = $this->city;
			$this->xsales_order['orders']['billing_postcode'] = $this->postcode;
			$this->xsales_order['orders']['billing_state'] = $this->state;
			
			$this->xsales_order['orders']['billing_country'] = $this->country;
			$this->xsales_order['orders']['payment_method'] = $this->payment_method_text;
			$this->xsales_order['orders']['date_purchased'] = 'NOW()';
			if ($this->payment_method == 'credit_card') {
				$this->xsales_order['orders']['orders_status'] = '1';
			}
			else {
				$this->xsales_order['orders']['orders_status'] = '9';
			}
			/*if ($this->payment_method == 'paypal') {
				$this->xsales_order['orders']['orders_status'] = '1';
			}
			else {
				$this->xsales_order['orders']['orders_status'] = '9';
			}*/
			$this->xsales_order['orders']['currency'] = 'EUR';
			$this->xsales_order['orders']['currency_value'] = AUDEUR_RATIO;
			
			$oCart = new Cart();
			$pr_ids = array();
			$cart_contents = $oCart->getContents(); 
			$cart_subtotal = 0;
			$shipping = 0;
			
			foreach ($cart_contents as $product_cart_details) {
				$product = Utils::getProducts(array($product_cart_details['p']));
				$product = $product[0];

				$attributes = array();

				$attributes_price = 0;
				foreach ($product_cart_details['attribs'] as $attr => $val) {
					$attribute = Utils::getAttributeDetails($attr);
					$value = Utils::getAttributeValueDetails($product['products_id'], $attr, $val);
					$attributes[] = array(
						'orders_products_id'		=> $product['products_id'],
						'products_options'			=> $attribute['products_options_name'],
						'products_options_values'	=> $value['products_options_values_name'],
						'options_values_price'		=> $value['options_values_price'],
						'price_prefix'				=> $value['price_prefix'],
						'options_sku'				=> $value['options_sku']
					);
					if ($value['options_values_price'] != 0) {
						if ($value['price_prefix'] == '+') {
							$attributes_price += $value['options_values_price'];
						}
						else {
							$attributes_price -= $value['options_values_price'];
						}
					}
				}

				$xsales_orders_products = array(
					'products_id'		=> $product['products_id'],
					'products_model'	=> $product['products_model'],
					'products_name'		=> str_replace('|', '--', $product['products_name']),
					'products_price'	=> $product['products_price'],
					'final_price'		=> $product['products_price'] + Utils::getPriceWithoutGST($attributes_price),
					'products_tax'		=> '10%',
					'products_quantity'	=> $product_cart_details['q'],
					'attributes'		=> $attributes
				);
				$this->xsales_order['orders_products'][] = $xsales_orders_products;
				$price = Utils::getPriceWithGST($product['products_price']) + $attributes_price;
				$product_total_price = number_format($price * $product_cart_details['q'], 2); 
				$cart_subtotal += $product_total_price;
			}
			
			$shipping = Utils::getShippingCostForOrderSubtotal($cart_subtotal);
			
			$this->xsales_order['orders_status_history']['orders_status_id'] = $this->xsales_order['orders']['orders_status'];
			$this->xsales_order['orders_status_history']['date_added'] = 'NOW()';
			$this->xsales_order['orders_status_history']['comments'] = 'penisplugs.eu order';
			
			$this->xsales_order['internal']['shipping'] = $shipping;
 
			$this->xsales_order['internal']['cart_subtotal'] = $cart_subtotal;
			$this->xsales_order['internal']['cc_number'] = $this->cc_number;
			$this->xsales_order['internal']['cc_code'] = $this->cc_code;
			$this->xsales_order['internal']['cc_name'] = $this->cc_name;
			$this->xsales_order['internal']['cc_year'] = $this->cc_year;
			$this->xsales_order['internal']['cc_month'] = $this->cc_month;
			$this->xsales_order['internal']['f_name'] = $this->f_name;
			$this->xsales_order['internal']['l_name'] = $this->l_name;
			$this->xsales_order['internal']['payment_method'] = $this->payment_method;
			
			$orders_total = array(
				'title'		=> 'Sub-Total:',
				'text'		=> Utils::getPriceNumberFormatAndCurrency($cart_subtotal),
				'value'		=> $cart_subtotal,
				'class'		=> 'ot_subtotal',
				'sort_order'=> 1
				
			);
			$this->xsales_order['orders_total'][] = $orders_total;
			
			$orders_total = array(
					'title'		=> 'Flat Rate:',
					'text'		=> Utils::getPriceNumberFormatAndCurrency($shipping),
					'value'		=> Utils::getPriceNumberFormat($shipping),
					'class'		=> 'ot_shipping',
					'sort_order'=> 2
			
			);
			$this->xsales_order['orders_total'][] = $orders_total;
			
			$orders_total = array(
					'title'		=> 'Total:',
					'text'		=> Utils::getPriceNumberFormatAndCurrency($shipping + $cart_subtotal),
					'value'		=> Utils::getPriceNumberFormat($shipping) + $cart_subtotal,
					'class'		=> 'ot_total',
					'sort_order'=> 4
						
			);
			$this->xsales_order['orders_total'][] = $orders_total;
			
		}
		
		public function getSerializedXsalesOrder() {
			return serialize($this->xsales_order);
		}
		
		public function setSerializedXsalesOrder($str) {
			$this->xsales_order = unserialize($str);
		}
		
		public function validate() {
			if ($this->payment_method == '') {
				return false;
			}
			
			$this->convertToXsalesOrder();
			return true;
		}
		
		public function getErrorMsg() {
			return 'layout/order_error_msg.php';
		}
		
		public function getSuccessMsg() {
			return 'layout/order_success_msg.php';
		}
		
		public function processMsg() {
			return 'layout/order_processing_msg.php';
		}
		
		public function getErrorPaymentMsg() {
			return 'layout/order_error_msg.php';
		}
		
		public function getPaypalPaymentForm() {
			return 'layout/order_paypal_payment_form.php';
		}

    		/**
		 * Gets the checkout url from Zippay
		 *
		 * @return string
     		 */
    		public function getZippayCheckoutUrl() 
    		{

		   //var_dump($this->xsales_order); exit;
		   require_once(LOCAL_PATH.'/vendor/zipmoney/merchantapi-php/autoload.php');

        	   $returnUrl = Utils::getMainUrl() . 'cs'; 
        	   $cancelUrl = Utils::getMainUrl() . 'checkout'; 
        	   $currencyCode = 'AUD';

		   //------------------------------
		   //shopper address
		   //------------------------------
		   $name = explode(' ', $this->xsales_order['orders']["billing_name"]);		
		   $cAddData = array( "line1"=>$this->xsales_order['orders']["billing_street_address"], 
			"city"=>$this->xsales_order['orders']["billing_city"], "state"=>$this->xsales_order['orders']["billing_state"], 
			"postal_code"=>$this->xsales_order['orders']["billing_postcode"], 
			//"country"=>$this->xsales_order['orders']["billing_country"], 
			"country"=>'AU', 'first_name'=>$name[0], 'last_name'=>$name[1] ); 
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
		   $shopperData = array( 'title'=>'MR/MS', 'first_name'=>$name[0], 'last_name'=>$name[1], 'middle_name'=>'', 
			'phone'=>'0400000000', 'email'=>$this->xsales_order['orders']["customers_email_address"], 
			'birth_date'=>"1900-01-01", 'gender'=>'Male', 'statistics'=>$shopperStats, 'billing_address'=>$shopperAddress ); 
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
        	   foreach ($this->xsales_order['orders_products'] as $item) { 
          		//$productImage = $product->getProductsImage(); 
	  		//$pImgUrl = "http://xsales.com.au/images/".$productImage;
			$pImgUrl = ''; $taxpc = str_replace('%', '', $item["products_tax"]);
			$set_price = number_format((float)$item["products_price"] + (($item["products_price"] * $taxpc) / 100), 2, '.', ','); 

			$itemTotalValue += $set_price * $item['products_quantity'];

	  		$anItem = array( "name"=>$item["products_name"], "amount"=>floatval($set_price), 
				   "quantity"=>intval($item["products_quantity"]), "type"=>"sku", "reference"=>$item["products_id"], 
				   "product_code"=>$item["products_id"] ); 
	  		$itemDetails = new \zipMoney\Model\OrderItem($anItem);
	  		array_push($oItems, $itemDetails);
        	   }

		   //add shipping price to the order
		   $anItem = array("name"=>"Shipping and Handling", "amount"=>floatval($this->xsales_order['internal']['shipping']), 
					"quantity"=>1, "type"=>"shipping"); 
		   $itemDetails = new \zipMoney\Model\OrderItem($anItem); array_push($oItems, $itemDetails);
	           $itemTotalValue += $this->xsales_order['internal']['shipping']; 	
		   $itemValue = number_format((float)$itemTotalValue, 2, '.', ','); 

		   $orderDetails = new \zipMoney\Model\CheckoutOrder(
				array("reference"=>$this->getXsalesOrderId(), "amount"=>floatval($itemValue), "currency"=>$currencyCode, 
					"shipping"=>$shippingDetails, "items"=>$oItems ) ); //var_dump($orderDetails); exit;


		   //-------------------------------
		   //create checkout via api
		   //-------------------------------
		   $configDetails = new \zipMoney\Model\CheckoutConfiguration( array( 'redirect_uri'=> $returnUrl ) ); 
		   $requestParams = array('shopper'=>$shopperDetails,'order'=>$orderDetails,'config'=> $configDetails, 
					  'created'=>date('Y-m-d H:i:s'), 'state'=>'created'); //var_dump($requestParams); exit;

		   // Configure API key authorization: Authorization
		   //\zipMoney\Configuration::getDefaultConfiguration()->setApiKey('Authorization', 'yhd7bxotuh/EHxKuJp9yH09Rn/8Hbz1EcAmT9S62P9U=');
	  	   \zipMoney\Configuration::getDefaultConfiguration()->setApiKey('Authorization', 'd1bdEnS8ocFPsW4foatlq0HgKAlqr520R1F6v2yYkCU=');
		   \zipMoney\Configuration::getDefaultConfiguration()->setApiKeyPrefix('Authorization', 'Bearer');
		   //\zipMoney\Configuration::getDefaultConfiguration()->setEnvironment('sandbox'); // Allowed values are  ( sandbox | production )
		   \zipMoney\Configuration::getDefaultConfiguration()->setEnvironment('production'); // Allowed values are  ( sandbox | production )
		   //\zipMoney\Configuration::getDefaultConfiguration()->setPlatform('Php/5.6'); // E.g. Magento/1.9.1.2
	
		   $api_instance = new \zipMoney\Api\CheckoutsApi();
		   $request = new \zipMoney\Model\CreateCheckoutRequest($requestParams); //var_dump($request); exit; 
        	   //$requestjson = $request->__toString(); var_dump($requestjson); exit;

		   try { 
			//create checkout api call
			$result = $api_instance->checkoutsCreate($request);
    			//$result = $api_instance->checkoutsCreate($requestjson);  
    			//echo '<pre>'; var_dump($result); echo '</pre>'; exit;
			$resultURI = $result->getUri(); 
			return $resultURI;

		   } catch (Exception $e) {
    			echo 'Exception when calling CheckoutsApi->checkoutsCreate: ', $e->getMessage(), PHP_EOL; 
			$result = '';  return $result;	
		   }

    		}		

		
	}

?>
