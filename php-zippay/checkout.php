<?php

	include_once('incl/setup.php');
	
	include_once('classes/db.php');
	include_once('classes/utils.php');
	include_once('classes/page.php');
	include_once('classes/cart.php');
	
	$enable_ssl = true;
	DB::init();
	
	$oPage = new Page('checkout');
	$oCart = new Cart();
	$metatage="";
	include_once('layout/top.php');
	
	$h1Txt = $oPage->getTitle();
	$h2Txt = '';
	$landingTxt = $oPage->getDescription();
	
?>

<?php include_once('layout/header.php'); ?>
<?php include_once('layout/checkout.php'); ?>
<?php include_once('layout/footer.php'); ?>

<?php 
	
	include_once('layout/bottom.php');
	include_once('incl/close.php');

?>