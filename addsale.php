<?php session_start(); ?>
<?php var_dump($_SESSION); var_dump($_POST); ?>
<?php
include ("../settings.php");
include ("../language/$cfg_language");
include ("../classes/db_functions.php");
include ("../classes/security_functions.php");
include ("../classes/display.php");

$lang=new language();
$dbf=new db_functions($cfg_server,$cfg_username,$cfg_password,$cfg_database,$cfg_tableprefix,$cfg_theme,$lang);
$dbf_osc=new db_functions($cfg_osc_server,$cfg_osc_username,$cfg_osc_password,$cfg_osc_database,'',$cfg_theme,$lang);
$sec=new security_functions($dbf,'Sales Clerk',$lang);
$display=new display($dbf->conn,$dbf_osc->conn,$cfg_theme,$cfg_currency_symbol,$lang);
$tablenameuser = $cfg_tableprefix.'users';
$auth = $dbf->idToField($tablenameuser,'type',$_SESSION['session_user_id']);
$userLoginName= $dbf->idToField($tablenameuser,'username',$_SESSION['session_user_id']);

$table_bg=$display->sale_bg; $num_items = 0;
//$num_items=count($_SESSION['items_in_sale']);
$sale_items1 = explode(' ', $_SESSION['items_in_sale']);
$sale_items = array_diff($sale_items1,array(""));
$num_items = count($sale_items); //echo $num_items;
if($num_items==0){
   echo "<b>$lang->youMustSelectAtLeastOneItem</b><br>";
   echo "<a href=javascript:history.go(-1)>$lang->refreshAndTryAgain</a>";
   exit();
}
if(!$sec->isLoggedIn()) { header ("location: ../login.php"); exit(); } 
?>
<?php
$customers_table=$cfg_tableprefix.'customers';
$sales_items_table=$cfg_tableprefix.'sales_items';
$sales_table=$cfg_tableprefix.'sales';
$products_table='products';

//general sale info
$paid_with=isset($_POST['payment-type'])?$_POST['payment-type']:'';
$comment=isset($_POST['comment'])?$_POST['comment']:'';
$customer_name = $dbf->idToFieldc($customers_table,'customers_firstname',$_SESSION['current_sale_customer_id']).' '.
		 $dbf->idToFieldc($customers_table,'customers_lastname',$_SESSION['current_sale_customer_id']);
//totals
$finalTax=number_format(floatval($_SESSION['taxtotal']), 2);
$sale_total_cost=number_format(floatval($_SESSION['grandtotal']), 2);
$subtotal=number_format(floatval($_SESSION['subtotal']), 2);
$temp_total_items_purchased=$num_items;
$amtender=isset($_POST['amount-tendered'])? number_format(floatval($_POST['amount-tendered']), 2) : ''; 
$amtenderchng = isset($_POST['amount-tendered'])? number_format(($sale_total_cost - $amtender), 2) : "";

//insert sale into db
$todaysDate=date("Y-m-d H:i:s");
$field_names=array('date','customer_id','sale_sub_total','sale_total_cost','paid_with','items_purchased','sold_by',
			'comment','amount_tendered','amount_returned');
$field_data=array($todaysDate,$_SESSION['current_sale_customer_id'],$subtotal,$sale_total_cost,$paid_with,$temp_total_items_purchased,
			$_SESSION['session_user_id'],$comment,$amtender,$amtenderchng);
$dbf->insert($field_names,$field_data,$sales_table,false);
$saleID=mysql_insert_id($dbf->conn); 

//insert sale items into db
$field_names=array('sale_id','item_id','quantity_purchased','item_unit_price','item_tax_percent','item_total_tax','item_total_cost');
if(isset($_SESSION['items_in_sale'])) {
  $item_info=explode(' ',$_SESSION['items_in_sale']); $num_items=count($item_info);
  $carthtml = ''; $sub_total = 0.00; $tax_total = 0.00; $grand_total = 0.00;
  for($k=0;$k<$num_items;$k++) { 
    $temp_item_id=$item_info[$k]; if($temp_item_id == ""){ continue; } 
    $tQuery = 'SELECT tax_rate FROM tax_rates tr, products p 
  		WHERE p.products_tax_class_id=tr.tax_rates_id and p.products_id="'.$temp_item_id.'"'; 
    $tResult = mysql_fetch_row(mysql_query($tQuery,$dbf_osc->conn));  
    $pQuery = "SELECT p.products_price, pd.products_name FROM products as p,products_description as pd 
  		WHERE p.products_id=pd.products_id and p.products_id='".$temp_item_id."'";  
    $pResult = mysql_fetch_row(mysql_query($pQuery,$dbf_osc->conn)); 
    $retailtaxpc = number_format(floatval($tResult[0]),0); 
    $retailtax = number_format(floatval(($tResult[0] * $pResult[0])/100), 2); 
    $retailprice = number_format(floatval($pResult[0] + (($tResult[0] * $pResult[0])/100)), 2);
    //cart html 
    $carthtml .= '<tr id="'.$temp_item_id.'">';
    $carthtml .= '<td class="pronamedtls"><span class="d-block">'.$pResult[1].'</span></td>';
    $carthtml .= '<td class="uprice">'.number_format(floatval($pResult[0]),2).'</td><td class="taxpc">'.$retailtaxpc.'</td>';
    $carthtml .= '<td><div class="quantity"><input type="number" min="1" max="9" step="1" value="1"></div></td>';
    $carthtml .= '<td class="totalprice">'.$retailprice.'</td>';
    $carthtml .= '<td class=""></td>';
    $carthtml .= '</tr>';
    //details into db
    $temp_quantity_purchased = 1;
    $temp_item_unit_price = number_format(floatval($pResult[0]),2); 
    $temp_item_tax_percent = $retailtaxpc;
    $temp_item_tax = $retailtax;
    $temp_item_cost = $retailprice;
    //array for insert into db
    $field_data=array("$saleID","$temp_item_id","$temp_quantity_purchased","$temp_item_unit_price","$temp_item_tax_percent",
			"$temp_item_tax","$temp_item_cost");
    $dbf->insert($field_names,$field_data,$sales_items_table,false);
  } 
}
?>

<?php include "../top.php" ?>
<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">
<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">
<!-- Main Content -->
<div id="content">
<?php include "../navigation.php" ?>

<!-- Begin Page Content -->
<div class="pagebody" style="margin-top: -1.5rem">
<div class="container-fluid">	
	<div class="row">
	<div class="col-sm-7 possear__leftpanel bg-white" style="margin:0 auto;">
	<div class="pos__search__result">
	<div class="messages"></div>
	<?php $now=date("F j, Y, g:i a"); 
	      echo "<center>$now<br><h4>$lang->orderBy: $customer_name [$lang->paidWith $paid_with]</h4>"; ?>
	<div class="searchtable">
		<table class="search__table">
		<thead><tr><th class="themecolor">Product Name</th><th class="themecolor">Unit Price</th>
			<th class="themecolor">Tax(%)</th><th class="themecolor">Quantity</th>
			<th class="themecolor">Total(AUD)</th><th class="themecolor">&nbsp;</th></tr>
		</thead>
		<tbody><?php if(!empty($_SESSION['items_in_sale'])){ echo $carthtml; } ?></tbody>
		</table>
	</div>
	<div class="sels__search_tablebottom">
	<div class="row">
	<div class="col-7">
		<div class="sels__stab__comnt">
		<h6>Sale Comment</h6>
		<textarea class="form-control" placeholder="Place your comment here before sale..."></textarea>
		</div>
	</div>
	<div class="col-5 sels__search_tablebottom_right">
		<div class="sels__stab__comnt_right">
		<table width="100%">
		<tr>
			<td class="text-right">Sale Sub Total: AUD($)</td>
			<td class="text-left sub-total">
			<?php if(!empty($_SESSION['items_in_sale'])){ echo $subtotal; } else { echo 0.00; } ?>
			</td>
		</tr>
		<tr>
			<td class="text-right">Tax: AUD($)</td>
			<td class="text-left sales-tax">
			<?php if(!empty($_SESSION['items_in_sale'])){ echo $finalTax; } else { echo 0.00; } ?>
			</td>
		</tr>
		<tr>
			<td class="text-right themecolor font-weight-bold">Sale Total Cost: AUD($)</td>
			<td class="text-left themecolor font-weight-bold grand-total">
			<?php if(!empty($_SESSION['items_in_sale'])){ echo $sale_total_cost; } else { echo 0.00; } ?>
			</td>
		</tr>
		</table>
		</div>
	</div>
	</div>
	</div>
	</div>
	<?php
	  if($cfg_address!=''){ $temp_address=nl2br($cfg_address); echo "$lang->address: $temp_address <br>"; }
	  if($cfg_phone!=''){ echo "$lang->phoneNumber: $cfg_phone <br>"; }
	  if($cfg_email!=''){ echo "$lang->email: $cfg_email <br>"; }
	  if($cfg_fax!=''){ echo "$lang->fax: $cfg_fax <br>"; }
	  if($cfg_website!=''){ echo "$lang->website <a href=$cfg_website>$cfg_website</a> <br>"; }
	  if($cfg_other!=''){ echo "$lang->other: $cfg_other <br>"; }
	?>
	</div> <!-- end div class="col-sm-7 possear__leftpanel bg-white" -->
	</div> <!-- end row -->
<br><br>

<?php
$sec->closeSale();
$dbf->closeDBlink();
?>

<SCRIPT Language="Javascript">
/*
This script is written by Eric (Webcrawl@usa.net)
For full source code, installation instructions, 100's more DHTML scripts, and Terms Of
Use, visit dynamicdrive.com
*/
function printit(){  
if (window.print) {
    window.print() ;  
} else {
    var WebBrowser = '<OBJECT ID="WebBrowser1" WIDTH=0 HEIGHT=0 CLASSID="CLSID:8856F961-340A-11D0-A96B-00C04FD705A2"></OBJECT>';
    document.body.insertAdjacentHTML('beforeEnd', WebBrowser);
    WebBrowser1.ExecWB(6, 2);//Use a 1 vs. a 2 for a prompting dialog box    WebBrowser1.outerHTML = "";  
}}
</script>

<SCRIPT Language="Javascript">  
var NS = (navigator.appName == "Netscape");
var VERSION = parseInt(navigator.appVersion);
if (VERSION > 3) {
    document.write('<div style="text-align:center;"><form><input type=button value="Print" name="Print" onClick="printit()"></form></div>');        
}
</script>

</div>
</div>
<!-- /.container-fluid -->
</div>
<!-- End of Main Content -->

<!-- Footer -->
<?php include "../footer.php" ?>
<!-- End of Footer -->
</div>
<!-- End of Content Wrapper -->
</div>

<?php include "../bottom.php" ?> 
