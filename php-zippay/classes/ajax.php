<?php

	class Ajax {
		
		private $action = '';
		private $postData = array();
		private $decodedData = array();
		
		public function Ajax() {
			
		}
			
		public function setPostData($data) {
			$data_test = base64_decode(urldecode($data['d']));
			$data_test = explode('|', $data_test);
			$this->action = $data_test[1];
			$this->postData = $data;
			$this->decodedData = $data_test;
		}
		
		public function doStuff() {
			switch ($this->action) {
				case 'add_to_cart':
					$oCart = new Cart();
					$pr_id = $this->decodedData[0];
					//$q = $this->postData['q'];
					$q = '1';
					$attributes = $this->postData['att'];					
					$oCart->add($pr_id, $q, $attributes);
					$this->show($oCart->getLayoutFile(), $oCart);
				break;
				
				case 'remove_from_cart':
					$oCart = new Cart();
					$pr_id = $this->decodedData[0];
					$attributes = $this->decodedData[2];
					$oCart->remove($pr_id, $attributes);
					$this->show($oCart->getLayoutFile(), $oCart);
				break;
					
				case 'reload_cart_count':
					$oCart = new Cart();
					$this->output(count($oCart->getContents()));
				break;
				
				case 'show_cart':
					$oCart = new Cart();
					$this->show($oCart->getLayoutFile(), $oCart);
				break;
				
				case 'update_quantity':
					$oCart = new Cart();
					$pr_id = $this->decodedData[0];
					$q = $this->postData['q'];
					$attributes = $this->postData['att'];
					$oCart->update($pr_id, $q, $attributes);
					$this->show($oCart->getLayoutFile(), $oCart);
				break;
				
				case 'submit_order':
					$oOrder = new Order();
					$oOrder->setPostData($this->postData);
					if (!$oOrder->validate()) {
						$this->show($oOrder->getErrorMsg(), $oOrder);
					}
					else {
						$this->show($oOrder->processMsg(), $oOrder);
					}
				break;
				
				case 'process_order':
					$oOrder = new Order();
					$oOrder->setSerializedXsalesOrder($this->decodedData[2]);
					$res = $oOrder->process(); 
					if ($res == 'success') {
						$this->show($oOrder->getSuccessMsg(), $oOrder);
					}
					elseif ($res == 'failure') {
						$this->show($oOrder->getErrorPaymentMsg(), $oOrder);
					}
					elseif ($res == 'get_paypal_form') {
						$this->show($oOrder->getPaypalPaymentForm(), $oOrder);
					}
					elseif(strstr($res, 'zipmoney.com.au')){ 
					   echo 'Checkout Generated Successfully .. Redirecting to Zippay';
					   echo '<meta http-equiv="refresh" content="3;url='.$res.'" />';
					}
					else {
						$this->show($oOrder->getErrorPaymentMsg(), $oOrder);
					}
				break;
				
				case 'clear_data':
					$oCart = new Cart();
					$oCart->destroy();
				break;
				
				case 'contact_form_submit':
					Utils::sendContactEmail($this->postData);
					$this->show('layout/contact_us_msg.php');
					break;
					
				case 'load_cart_totals':
					$country = $this->postData['c'];
					//$country = 'India';
					$oCart = new Cart();
					$cart_subtotal = $oCart->calculate_subtotal();
					$obj = array(
							'country' => $country,
							'cart_subtotal' => $cart_subtotal
					);
					$this->show('layout/ajax_cart_totals.php', $obj);
					break;
				
				case 'load_checkout_totals2':
					$country = $this->postData['c'];
					$oCart = new Cart();
					$cart_subtotal = $oCart->calculate_subtotal();
					$obj = array(
							'country' => $country,
							'cart_subtotal' => $cart_subtotal
					);
					$this->show('layout/ajax_checkout_totals2.php', $obj);
					break;
				
				case 'save_checkout_country':
					$oCart = new Cart();
					$country = $this->postData['c'];
					$oCart->saveCheckoutCountry($country);
					break;
				
				default:
					;
				break;
			}
		}
		
		public function show($file, $obj = null) {
			include_once($file);
		}
		
		public function output($s) {
			echo $s;
		}
		
	}

?>
