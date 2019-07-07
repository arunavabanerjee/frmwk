<?php

	include_once('incl/setup.php');
	
	include_once('classes/db.php');
	include_once('classes/utils.php');
	include_once('classes/page.php');
	include_once('classes/cart.php');
	//$enable_ssl = true;
	DB::init();
	
	$oPage = new Page('checkout_success');
	$oCart = new Cart();
	$title="Checkout Success";
	$description="";
	$keyword="";
	$metatage="";
	include_once('layout/top.php');
	
	$h1Txt = $oPage->getTitle();
	$h2Txt = '';
	$landingTxt = $oPage->getDescription();

	if(isset($_GET['checkoutId']) && !empty($_GET['checkoutId'])){
	  //create the charge  
	  require_once(LOCAL_PATH.'/vendor/zipmoney/merchantapi-php/autoload.php');
	  // Configure API key authorization: Authorization
	  //\zipMoney\Configuration::getDefaultConfiguration()->setApiKey('Authorization', 'yhd7bxotuh/EHxKuJp9yH09Rn/8Hbz1EcAmT9S62P9U=');
	  \zipMoney\Configuration::getDefaultConfiguration()->setApiKey('Authorization', 'd1bdEnS8ocFPsW4foatlq0HgKAlqr520R1F6v2yYkCU=');
	  \zipMoney\Configuration::getDefaultConfiguration()->setApiKeyPrefix('Authorization', 'Bearer');
	  //\zipMoney\Configuration::getDefaultConfiguration()->setEnvironment('sandbox'); // Allowed values are  ( sandbox | production )
	  \zipMoney\Configuration::getDefaultConfiguration()->setEnvironment('production'); // Allowed values are  ( sandbox | production )
	  //\zipMoney\Configuration::getDefaultConfiguration()->setPlatform('Php/5.6'); // E.g. Magento/1.9.1.2	
	  try { 
		if($_GET['result'] == 'approved'){
	  	   $id = $_GET['checkoutId']; $apiResult = $_GET['result']; $zippayCId = $_GET['customerId'];
		} elseif($_GET['result'] == 'cancelled') {
		   $id = $_GET['checkoutId']; $apiResult = $_GET['result']; 
		} 
		$api_instance = new \zipMoney\Api\CheckoutsApi();
    	        $checkoutObj = $api_instance->checkoutsGet($id); 
		//echo '<pre>'; print_r($checkoutObj); echo '</pre>'; exit;

		//if result approved, then update db
		if($apiResult == 'approved'){ 	
		  //create a charge object 
		  try { 
		     $api_instance = new \zipMoney\Api\ChargesApi();
		     //authority model 
		     $authorityData = array('type'=>'checkout_id', 'value'=>$checkoutObj->getId()); 
		     $authorityDetails = new \zipMoney\Model\Authority($authorityData); 
		     //charge order data 
		     $chargeOrderData = array('reference'=>$checkoutObj->getOrder()->getReference(), 'shipping'=>$checkoutObj->getOrder()->getShipping(), 
						 'items'=>$checkoutObj->getOrder()->getItems(), 'cart_reference'=>'' ); 
		     $chargeOrderDetails = new \zipMoney\Model\ChargeOrder($chargeOrderData);
		     //charge request data
		     $chargeRequestData = array('authority'=>$authorityDetails, 'reference'=>$checkoutObj->getOrder()->getReference(),
						'amount'=>$checkoutObj->getOrder()->getAmount(), 'currency'=>$checkoutObj->getOrder()->getCurrency(), 
						'capture'=>true, 'order'=>$chargeOrderDetails, 'metadata'=>'' );
		     $body = new \zipMoney\Model\CreateChargeRequest($chargeRequestData);  
		     $idempotency_key = $checkoutObj->getId(); 
		     //get the charge
		     $chargeObj = $api_instance->chargesCreate($body, $idempotency_key); 
		     //echo '<pre>'; print_r($chargeObj); echo '</pre>'; exit; 

		  } catch (Exception $e) {
    		      echo 'Exception when calling ChargesApi->chargesCreate: ', $e->getMessage(), PHP_EOL;
		  }

		  //retrieve order ids
		  $orderids = $checkoutObj->getOrder()->getReference();  


		} else {
		   //retrieve order ids
		   $orderids = "";
		   throw new Exception('Payment Not Approved By Zippay / Transaction Cancelled');
		} 

	  } catch (Exception $e) {
		$error_msg = 'Exception Generated When Processing Payment: '. $e->getMessage(). PHP_EOL;
	  } 

	} else {
	    //print_r($_POST) ;
	    $orderids=$_POST['custom'];
	    //$orderids='43476';
	}

	if( $orderids != ""){
	  //echo "UPDATE `orders_retail` SET `orders_status` = '1' WHERE `orders_retail`.`orders_id` = $orderids";
	  //echo "UPDATE `orders_status_history_retail` SET `orders_status_id` = '1' WHERE `orders_id` = $orderids";
	  $updateorder=mysql_query("UPDATE `orders_retail` SET `orders_status` = '1' WHERE `orders_retail`.`orders_id` = $orderids");
	  $updateordertwo=mysql_query("UPDATE `orders_status_history_retail` SET `orders_status_id` = '1' WHERE `orders_id` = $orderids");
        }
	
?>

<?php include_once('layout/header.php'); ?>
<?php //include_once('layout/checkout_success.php'); ?>

<?php if($orderids != "") { ?>
 <?php   include_once('layout/checkout_success.php'); ?>
<?php } elseif($orderids == "") { ?>
<?php    include_once('layout/order_error_msg.php'); ?>
<?php } ?>

<?php include_once('layout/footer.php'); ?>

<?php include_once('layout/bottom.php');
      include_once('incl/close.php');
?>
