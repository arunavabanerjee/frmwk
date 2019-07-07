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
	//print_r($_POST) ;
	$orderids=$_POST['custom'];
	//$orderids='43476';
	//echo "UPDATE `orders_retail` SET `orders_status` = '1' WHERE `orders_retail`.`orders_id` = $orderids";
	//echo "UPDATE `orders_status_history_retail` SET `orders_status_id` = '1' WHERE `orders_id` = $orderids";
	$updateorder=mysql_query("UPDATE `orders_retail` SET `orders_status` = '1' WHERE `orders_retail`.`orders_id` = $orderids");
	$updateordertwo=mysql_query("UPDATE `orders_status_history_retail` SET `orders_status_id` = '1' WHERE `orders_id` = $orderids");
	
?>

<?php include_once('layout/header.php'); ?>
<?php include_once('layout/checkout_success.php'); ?>
<?php include_once('layout/footer.php'); ?>

<?php 
	
	include_once('layout/bottom.php');
	include_once('incl/close.php');

?>