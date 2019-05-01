<?php
session_start();  
include ("../classes/db_functions.php");
include ("../classes/security_functions.php");
include ("../classes/display.php");
include ("../settings.php");
include ("../language/$cfg_language");
$lang=new language(); 
$dbf=new db_functions($cfg_server,$cfg_username,$cfg_password,$cfg_database,$cfg_tableprefix,$cfg_theme,$lang);
$dbf_osc=new db_functions($cfg_osc_server,$cfg_osc_username,$cfg_osc_password,$cfg_osc_database,'',$cfg_theme,$lang);
$sec=new security_functions($dbf,'Sales Clerk',$lang);
$display=new display($dbf->conn,$dbf_osc->conn,$cfg_theme,$cfg_currency_symbol,$lang);
$table_bg=$display->sale_bg;
$items_table="$cfg_tableprefix".'items'; 
$tablename = $cfg_tableprefix.'users';
$auth = $dbf->idToField($tablename,'type',$_SESSION['session_user_id']);
$userLoginName= $dbf->idToField($tablename,'username',$_SESSION['session_user_id']);

if(!$sec->isLoggedIn()){ header ("location: ../login.php"); exit(); } 

/**
 * customer search 
 */
if(isset($_POST['clearbtn']) && ($_POST['clearbtn'] == "clear_customer")){
  unset($_SESSION["current_sale_customer_id"]); 
  unset($_POST["clearbtn"]); unset($_POST["customer"]);
}

if(empty($_SESSION['current_sale_customer_id'])){
	$customers_table="$cfg_tableprefix".'customers';
	// no customer details have been searched
	if(!isset($_POST['customer_search']) && !isset($_POST['customer'])){
	    $cust_data = '<h4 class="smalltitle themecolor">Find Customer: ';
            $cust_data .='<button type="button" class="btn themegradirn-bg text-white ml-1 text-uppercase" style="padding:4px;line-height:1;font-size:10px;" data-toggle="modal" data-target="#addnewcustomer">ADD NEW</button></h4>';
	    $cust_data .= '<div class="form-group row">'; 
	    $cust_data .= '<div class="col-8"><input type="text" class="form-control" placeholder="Customer Name" name="customer_search" /></div>';
	    $cust_data .= '<div class="col-4"><button type="button" class="btn themegradirn-bg text-white ml-1 text-uppercase" onclick="jQuery(\'#findcustomer\').submit();">GO</button></div>';
	    $cust_data .= '</div>';
	} else { //if customer searched
	    if(isset($_POST['customer_search']) and $_POST['customer_search']!='') {
		$search=$_POST['customer_search'];
		$_SESSION['current_customer_search']=$search;
	 	$customer_result=mysql_query("SELECT customers_firstname, customers_lastname, customers_id FROM ".$customers_table." WHERE customers_lastname like \"%$search%\" or customers_firstname like \"%$search%\" or customers_id =\"$search\" ORDER by customers_lastname",$dbf->conn);
	    }elseif(isset($_SESSION['current_customer_search'])) {
	 	$search=$_SESSION['current_customer_search'];
	 	$customer_result=mysql_query("SELECT customers_firstname, customers_lastname, customers_id FROM ".$customers_table." WHERE customers_lastname like \"%$search%\" or customers_firstname like \"%$search%\" or customers_id =\"$search\" ORDER by customers_firstname",$dbf->conn);
	    }elseif($dbf->getNumRows($customers_table) >200){
	 	$customer_result=mysql_query("SELECT customers_firstname, customers_lastname, customers_id FROM ".$customers_table." ORDER by customers_firstname LIMIT 0,200",$dbf->conn);	
	    }else {
	 	$customer_result=mysql_query("SELECT customers_firstname, customers_lastname, customers_id FROM ".$customers_table." ORDER by customers_firstname",$dbf->conn);
	    }
	    $customer_title="<b><font class='smalltitle themecolor'> $lang->customers:</font></b>";
	    $cust_data = '<h6 align="left">'.$customer_title."</h6>"; 
	    $cust_data .= '<div class="row">';
            $cust_data .= "<div class=\"col-6\"><select style='margin-bottom:10px;padding:4px;border-radius:4px;width:109%;' name='customer'>";
	    while($row=mysql_fetch_assoc($customer_result)) {
		$id=$row['customers_id'];
 		$display_name=$row['customers_firstname'].' , '.$row['customers_lastname'];
 		$cust_data .= "<option value=$id "; 
		if($_POST['customer'] == $id){ $cust_data .= 'selected="true"'; $_SESSION['current_sale_customer_id'] = $id; }
		$cust_data .= ">$display_name</option>";
	    }
	    $cust_data .="</select></div>";
	    $cust_data .='<div class="col-6"><button type="button" class="btn themegradirn-bg text-white ml-1 text-uppercase" onclick="jQuery(\'#findcustomer\').submit();">SELECT</button></div>';
	    $cust_data .= '</div>';
	} 				
}
if(!empty($_SESSION['current_sale_customer_id'])){
  $customers_table="$cfg_tableprefix".'customers';
  $customer_result=mysql_query("SELECT customers_firstname, customers_lastname, customers_id FROM ".$customers_table." ".
				"WHERE customers_id ='".$_SESSION['current_sale_customer_id']."' ORDER by customers_firstname",$dbf->conn);
  $customer_title="<b><font class='smalltitle themecolor'> $lang->customers:</font></b>";
  $cust_data = '<h6 align="left">'.$customer_title."</h6>"; 
  $cust_data .= '<div class="row">';
  $cust_data .= "<div class=\"col-6\"><select style='margin-bottom:10px;padding:4px;border-radius:4px;width:109%;' name='customer'>";
  while($row=mysql_fetch_assoc($customer_result)) {
	$id=$row['customers_id'];
	$display_name=$row['customers_firstname'].' , '.$row['customers_lastname'];
	$cust_data .= "<option value=$id "; 
	//if($_POST['customer'] == $id){ $cust_data .= 'selected="true"'; $_SESSION['current_sale_customer_id'] = $id; }
	$cust_data .= ">$display_name</option>";
  }
  $cust_data .="</select></div>";
  $cust_data .='<div class="col-6">'; 
  $cust_data .='<input type="hidden" name="clearbtn" id="clearbtn" value="clear_customer"/>';
  $cust_data .='<button type="button" class="btn themegradirn-bg text-white ml-1 text-uppercase" onclick="jQuery(\'#findcustomer\').submit();">CLEAR</button>';
  $cust_data .= '</div></div>';
}

/**
 * item search and item default search  
 */
//if(isset($_POST['item_search'])  and $_POST['item_search']!='') { 
if(!empty($_POST['item_search_pname']) || !empty($_POST['item_search_pmpn']) 
	|| !empty($_POST['item_search_pbcode']) || !empty($_POST['item_search_pman']) ){
	//$search=$_POST['item_search']; 
	$name = !empty($_POST['item_search_pname']) ? $_POST['item_search_pname'] : "";
	$mpn = !empty($_POST['item_search_pmpn']) ? $_POST['item_search_pmpn'] : ""; 
	$bcode = !empty($_POST['item_search_pbcode']) ? $_POST['item_search_pbcode'] : ""; 
	$man = !empty($_POST['item_search_pman']) ? $_POST['item_search_pman'] : ""; 
	//search query
	$search = ''; $from = ''; 
	if(!empty($name)){ 
		$from .=',products_description pd'; 
		$search .= "and pd.products_name like \"%$name%\" "; 
	}
	if(!empty($mpn)){ 
		$from .= ',products_description pd,products_attributes pa'; 
		$search .="and p.products_id = pa.products_id and pa.mpn like \"%$mpn%\" "; 
	}
	if(!empty($bcode)){ 
		$from .= ',products_description pd,products_attributes pa'; 
		$search .="and p.products_id = pa.products_id and pa.mpn like \"%$bcode%\" "; 
	}
	if(!empty($man)){ 
		$from .= ',products_description pd,manufacturers m'; 
		$search .= "and p.manufacturers_id = m.manufacturers_id and m.manufacturers_name like \"%$man%\" "; 
	}
	$_SESSION['current_item_from_table']=$from;
	$_SESSION['current_item_search']=$search;
	//sale price added
	$query="SELECT p.products_id,pd.products_name,p.products_price,tr.tax_rate, 
		IF(s.status, s.specials_new_products_price, NULL) as specials_new_products_price, 
		IF(s.status, s.specials_new_products_price, p.products_price) as final_price
		FROM products p left join specials s on p.products_id = s.products_id, tax_rates tr, tax_class tc"; 
        $query .= $from;
	$query .= " WHERE p.products_status = '1' and p.products_quantity > 0 and p.products_id=pd.products_id and
			p.products_tax_class_id=tc.tax_class_id and tr.tax_class_id=tc.tax_class_id "; 
	$query .= $search; 
	$query .= " and pd.language_id=1 ORDER by pd.products_name";
  
} elseif(isset($_SESSION['current_item_search'])) { 
	$from = $_SESSION['current_item_from_table'];
	$search = $_SESSION['current_item_search']; 
	if(empty($from)){ $from = ''; }
	if(empty($search)){ $search = ''; }
	//sale price added 
	$query="SELECT p.products_id,pd.products_name,p.products_price,tr.tax_rate, 
		IF(s.status, s.specials_new_products_price, NULL) as specials_new_products_price, 
		IF(s.status, s.specials_new_products_price, p.products_price) as final_price
		FROM products p left join specials s on p.products_id = s.products_id,tax_rates as tr,tax_class as tc";
	$query .= $from;
	$query .= " WHERE p.products_status = '1' and p.products_quantity > 0 and p.products_id=pd.products_id and
			p.products_tax_class_id=tc.tax_class_id and tr.tax_class_id=tc.tax_class_id "; 
	$query .= $search;
	$query .= " and pd.language_id=1 ORDER by pd.products_name"; //echo $query;

} else {
	//sale price added
  	$query="SELECT p.products_id,pd.products_name,p.products_price,tr.tax_rate, 
		IF(s.status, s.specials_new_products_price, NULL) as specials_new_products_price, 
		IF(s.status, s.specials_new_products_price, p.products_price) as final_price
		FROM products_description as pd,products p left join specials s on p.products_id = s.products_id,tax_rates as tr,tax_class as tc
		WHERE p.products_status = '1' and p.products_quantity > 0 and p.products_id=pd.products_id and
			p.products_tax_class_id=tc.tax_class_id and 
			tr.tax_class_id=tc.tax_class_id and pd.language_id=1 
		ORDER by pd.products_name";
}
$item_result=mysql_query($query,$dbf_osc->conn); 
$count_items = mysql_num_rows($item_result);
$item_title = isset($_SESSION['current_item_search']) ? "<font class='smalltitle themecolor'>$lang->selectItem ($count_items)</font>":"<font class='smalltitle themecolor'>$lang->selectItem ($count_items)</font>";
$item_data = '<h4 class="smalltitle themecolor"> Result: '.$item_title.'</h4>';
$item_data .= '<ul class="searpro__result__list__dtls" name="items">';
while($row=mysql_fetch_assoc($item_result)){
	$id=$row['products_id'];
  	//sale price added
	if ($row['specials_new_products_price']){
		$unit_price=$row['specials_new_products_price'];
	}else{
		$unit_price=$row['products_price'];
	}
  	$tax_percent=$row['tax_rate'];
  	$option_value="$id"."/"."$unit_price"."/"."$tax_percent";
	$display_item="$row[products_name]";
	$item_data .= "<li value='$option_value'><a href='#'>$display_item</a></li>";
}
$item_data .= "</ul>";

/**
 * add to cart items
 * called via jquery post
 */
if(isset($_POST['pid'])){ //echo $_POST['pid']; 
 $sale_items = $_SESSION['items_in_sale']; 
 if(!isset($_SESSION['current_sale_customer_id']) && empty($sale_items)){ 
   $html = '<tr><td colspan="6">';
   $html .= '<p style="color:red;font-weight:bold">Need To Select Customer First To Start A Sale</p>';
   $html .= '</td></tr>';
 } else { //start a sale
    if(empty($sale_items)){ $sale_items = $_POST['pid'].' '; 
    } else{ $sale_items .= $_POST['pid'].' '; }  
    $_SESSION['items_in_sale'] = $sale_items; 
    //query for product values 
    $tQuery = 'SELECT tax_rate FROM tax_rates tr, products p 
		WHERE p.products_tax_class_id=tr.tax_rates_id and p.products_id="'.$_POST['pid'].'"'; 
    $tResult = mysql_fetch_row(mysql_query($tQuery,$dbf_osc->conn));  
    $pQuery = "SELECT p.products_price, pd.products_name FROM products as p,products_description as pd 
		WHERE p.products_id=pd.products_id and p.products_id='".$_POST['pid']."'";  
    $pResult = mysql_fetch_row(mysql_query($pQuery,$dbf_osc->conn)); 
    $retailtaxpc = number_format(floatval($tResult[0]),0);
    $retailprice = number_format(floatval($pResult[0] + (($tResult[0] * $pResult[0])/100)), 2);
    //append values
    $html = '<tr id="'.$_POST['pid'].'">';
    $html .= '<td class="pronamedtls"><span class="d-block">'.$pResult[1].'</span></td>';
    $html .= '<td class="uprice">'.number_format(floatval($pResult[0]),2).'</td><td class="taxpc">'.$retailtaxpc.'</td>';
    $html .= '<td><div class="quantity"><input type="number" min="1" max="9" step="1" value="1"></div></td>';
    $html .= '<td class="totalprice">'.$retailprice.'</td>';
    $html .= '<td class="deletepro"><i class="lnr lnr-cross-circle"></i></td>';
    $html .= '</tr>';
  }
  echo $html; die();
}

/**
 * delete items
 * called via jquery post
 */
if(isset($_POST['del'])){ 
 if($_POST['del']){ 
  $sale_items = $_SESSION['items_in_sale']; 
  $sale_items_arr = explode(' ',$sale_items); 
  $sale_items_mod = array_diff($sale_items_arr, array($_POST['pdelid'])); 
  $sale_items = implode(' ',$sale_items_mod); 
  $_SESSION['items_in_sale'] = $sale_items;
  $html = '<p style="color:red;font-weight:bold;"> Item with ID: '.$_POST['pdelid'].' removed </p>';
  echo $html; die();
 }
}

/**
 * update sessions
 * called via jquery post
 */
if(isset($_POST['updtses'])){
 if($_POST['updtses']){
   $_SESSION['subtotal'] = $_POST['subtotal'];
   $_SESSION['taxtotal'] =  $_POST['taxtotal'];
   $_SESSION['grandtotal'] = $_POST['grandtotal'];
   $html = 'Session Updated'; 
   echo $html; die(); 
 }
}

/**
 * remove sale 
 * called via jquery
 */
if(isset($_POST['remove'])){
 if($_POST['remove']){
   unset($_SESSION['subtotal']); unset($_SESSION['taxtotal']);
   unset($_SESSION['grandtotal']); unset($_SESSION["items_in_sale"]);
   //header ("location: sale_ui.php"); exit();
   $html = 'Session Removed'; 
   echo $html; die(); 
 }
}

/**
 * cart functions 
 * on page reload
 */
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
    $carthtml .= '<td class="deletepro"><i class="lnr lnr-cross-circle"></i></td>';
    $carthtml .= '</tr>'; 
    $sub_total += $retailprice; $tax_total += $retailtax; 
    $grand_total += $retailprice + $retailtax;
  } 
}

//var_dump($_SESSION); var_dump($_POST);

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
	<div class="col-sm-7 possear__leftpanel bg-white">
	<div class="pos__search__result">
	<div class="messages"></div>
	<div class="searchtable">
		<table class="search__table">
		<thead><tr><th class="themecolor">Product Name</th><th class="themecolor">Unit Price</th>
			<th class="themecolor">Tax(%)</th><th class="themecolor">Quantity</th>
			<th class="themecolor">Total(AUD)</th><th class="themecolor">&nbsp;</th></tr>
		</thead>
		<tbody><?php if(!empty($_SESSION['items_in_sale'])){ echo $carthtml; } ?></tbody>
		</table>
		<?php if(empty($_SESSION['items_in_sale'])){ 
		   echo "<center style=\"padding:100px;\"><h3>$lang->yourShoppingCartIsEmpty</h3></center>"; 	  
		}?>
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
			<?php if(!empty($_SESSION['items_in_sale'])){ echo $sub_total; } else { echo 0.00; } ?>
			</td>
		</tr>
		<tr>
			<td class="text-right">Tax: AUD($)</td>
			<td class="text-left sales-tax">
			<?php if(!empty($_SESSION['items_in_sale'])){ echo $tax_total; } else { echo 0.00; } ?>
			</td>
		</tr>
		<tr>
			<td class="text-right themecolor font-weight-bold">Sale Total Cost: AUD($)</td>
			<td class="text-left themecolor font-weight-bold grand-total">
			<?php if(!empty($_SESSION['items_in_sale'])){ echo $grand_total; } else { echo 0.00; } ?>
			</td>
		</tr>
		</table>
		</div>
	</div>
	</div>
	</div>
	<div class="sales__button__grp">
	<div class="sales__payment mb-3">
        <form id="sale" name="sale" action="addsale.php" method="post">
	<div class="row">
		<div class="col-7 text-right"><label class="m-0">Paid in AUD</label></div>
		<div class="col-5"> 
			<select class="form-control" name="payment-type">
				<option selected="selected">Cash</option>
				<option>Credit Card</option>
				<option>Other</option>
			</select>
		</div>
	</div>
	</div>
	<div class="sales__payment mb-1">
	<div class="row">
		<div class="col-7 text-right"><label class="m-0">Amount to be tender or received in AUD</label></div>
		<div class="col-5">
		 <input type="text" class="form-control" id="amount-tendered" name="amount-tendered" 
			value="<?php if(!empty($_SESSION['items_in_sale'])){ echo $grand_total; } else { echo 0.00; } ?>"/>
		</div>
	</div>
	</div>
	<div class="selb__grp mt-3 text-center">
		<button class="btn themegradirn-bg text-white w-100 fullwudth_btn add-sale" type="button">ADD SALE</button>
		<button class="btn bg-gray-700 text-white short_btn mt-3 clear-sale" type="button">CLEAR SALE</button>
		<!--<button class="btn themegradirn-bg text-white short_btn mt-3" type="button">ADD SALE</button>-->
	</div>
	</div>
	</form>
	</div>
	</div> <!-- end div class="col-sm-7 possear__leftpanel bg-white" -->			
	<div class="col-sm-5 searchingpro__right">
	<div class="searchpro__form">
		<form id="findcustomer" action="<?php echo $_SERVER["REQUEST_SCHEME"].'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']; ?>" method="post">
		<?php echo $cust_data; ?>
		</form>

		<form id="finditem" action="<?php echo $_SERVER["REQUEST_SCHEME"].'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']; ?>" method="post">	
		<h4 class="smalltitle themecolor">Search Item</h4>
		<div class="form-group row mb-0">
		<div class="col-6 mb-2">
			<input type="text" class="form-control" placeholder="Product Name" name='item_search_pname'>
		</div>
		<div class="col-6 mb-2">
			<input type="text" class="form-control" placeholder="Product MPN Number" name='item_search_pmpn'>
		</div>
		<div class="col-6 mb-2">
			<input type="text" class="form-control" placeholder="Barcode" name='item_search_pbcode'>
		</div>
		<div class="col-6 mb-2">
			<input type="text" class="form-control" placeholder="Manufacturer" name='item_search_pman'>
		</div>
		<div class="col-12 mb-2">
		  <button type="button" class="btn themegradirn-bg text-white text-uppercase" onclick="jQuery('#finditem').submit();">Find Product</button>
		</div>
		</div>
		</form>
	</div>
	<div class="searpro__result__list mb-3">
		<?php echo $item_data; ?>
	</div>
	</div> <!-- end div class="col-sm-5 searchingpro__right" -->
	</div> <!-- end row -->

<script type="text/javascript">
var jpost = jQuery.noConflict();
jpost('.searpro__result__list__dtls li').click(function(){ 
  var name = jpost(this).text(); //console.log(name);
  var value = jpost(this).val(); //console.log(value); 
  jpost.post("sale_ui.php", { pid : value })
       .done(function(data){
           if(jpost(data).closest('tr').attr('id') > 0){ 
	     jpost('.searchtable center').css('display', 'none'); 
	   }
           jpost('.search__table tbody').append(data);
           //make calculations and add to grand total 
	   var uprice = jpost(data).find('td.uprice').text(); //console.log(uprice); 
	   var taxpc = jpost(data).find('td.taxpc').text(); //console.log(taxpc);
	   var quant = jpost(data).find('input[type="number"]').val(); //console.log(quant);
	   var price = jpost(data).find('td.totalprice').text(); //console.log(price);
           var taxretailn = 0.00; var subtotalretailn = 0.00; var grandtotalretailn = 0.00;
	   //cals tax
	   if(jpost('.sels__stab__comnt_right .sales-tax').text() == '0.00'){
              var taxretail = ((parseFloat(taxpc) * parseFloat(uprice)) / 100).toFixed(2);    
              jpost('.sels__stab__comnt_right .sales-tax').text(taxretail);
	   }else{
	      var taxretail = (parseFloat(taxpc) * parseFloat(uprice)) / 100;
	      var prevtaxretail = parseFloat(jpost('.sels__stab__comnt_right .sales-tax').text()); 
	      taxretailn = (taxretail + prevtaxretail).toFixed(2); 
              jpost('.sels__stab__comnt_right .sales-tax').text(taxretailn); 		   
	   }
	   //cals subtotal
	   if(jpost('.sels__stab__comnt_right .sub-total').text() == '0.00'){
              var subtotalretail = parseFloat(uprice).toFixed(2);    
              jpost('.sels__stab__comnt_right .sub-total').text(subtotalretail);
	   }else{
	      var subtotalretail = parseFloat(uprice);
	      var prevsubtotalretail = parseFloat(jpost('.sels__stab__comnt_right .sub-total').text()); 
	      subtotalretailn = (subtotalretail + prevsubtotalretail).toFixed(2); 
              jpost('.sels__stab__comnt_right .sub-total').text(subtotalretailn); 		   
	   }	  
	   //cals grandtotal
	   if(jpost('.sels__stab__comnt_right .grand-total').text() == '0.00'){
              var grandtotalretail = parseFloat(price).toFixed(2);    
              jpost('.sels__stab__comnt_right .grand-total').text(grandtotalretail);
	      jpost('#amount-tendered').val(grandtotalretailn);
	   }else{
	      var grandtotalretail = parseFloat(price);
	      var prevgrandtotalretail = parseFloat(jpost('.sels__stab__comnt_right .grand-total').text()); 
	      grandtotalretailn = (grandtotalretail + prevgrandtotalretail).toFixed(2); 
              jpost('.sels__stab__comnt_right .grand-total').text(grandtotalretailn); 	
	      jpost('#amount-tendered').val(grandtotalretailn); 	   
	   }
	   jpost.post("sale_ui.php", { updtses : true, grandtotal : grandtotalretailn, 
					subtotal : subtotalretailn, taxtotal: taxretailn })
	   .done(function(data){ console.log(data); });
   	}); 
});
jpost(document).on("click", '.search__table .deletepro', function(event){ 
  var value = jpost(this).parent().attr('id'); //console.log(value);
  var value = jpost(this).parent().attr('id'); //console.log(value); 
  var uprice = jpost(this).parent().find('td.uprice').text(); //console.log(uprice); 
  var taxpc = jpost(this).parent().find('td.taxpc').text(); //console.log(taxpc);
  var quant = jpost(this).parent().find('input[type="number"]').val(); //console.log(quant);
  var price = jpost(this).parent().find('td.totalprice').text(); //console.log(price);

  jpost(this).parent().remove();
  jpost.post("sale_ui.php", { pdelid : value, del : true })
       .done(function(data){
           if(jpost('.search__table tbody').children().length == 0){
	     jpost('.searchtable center').css('display', 'block');
	   }
	   jpost('.pos__search__result .messages').append(data); 
	   setTimeout(function() {
  	     jpost(".pos__search__result .messages").fadeOut().empty();
	   }, 3000);
	   //cals tax
      	   var taxretail = (parseFloat(taxpc) * parseFloat(uprice)) / 100;
	   var prevtaxretail = parseFloat(jpost('.sels__stab__comnt_right .sales-tax').text()); 
	   var taxretailn = (prevtaxretail - taxretail).toFixed(2); 
	   if(taxretailn < 0){ taxretailn = 0.00; }
           jpost('.sels__stab__comnt_right .sales-tax').text(taxretailn);
	   //cals subtotal
	   var subtotalretail = parseFloat(uprice);
	   var prevsubtotalretail = parseFloat(jpost('.sels__stab__comnt_right .sub-total').text()); 
	   var subtotalretailn = (prevsubtotalretail - subtotalretail).toFixed(2); 
           if(subtotalretailn < 0){ subtotalretailn = 0.00; }
           jpost('.sels__stab__comnt_right .sub-total').text(subtotalretailn);	  
	   //cals grandtotal
      	   var grandtotalretail = parseFloat(price);
	   var prevgrandtotalretail = parseFloat(jpost('.sels__stab__comnt_right .grand-total').text()); 
	   var grandtotalretailn = (prevgrandtotalretail - grandtotalretail).toFixed(2); 
	   if(grandtotalretailn < 0){ grandtotalretailn = 0.00; }
           jpost('.sels__stab__comnt_right .grand-total').text(grandtotalretailn); 	
	   jpost('#amount-tendered').val(grandtotalretailn); 
	   //updt session
	   jpost.post("sale_ui.php", { updtses : true, grandtotal : grandtotalretailn, 
					subtotal : subtotalretailn, taxtotal: taxretailn })
	   .done(function(data){ console.log(data); });
       });
});
/*jpost('.add-sale').click(function(){ 
   var paymentOpt = jpost('#payment-type option:selected').val();
   var amountTendered = jpost('#amount-tendered').val();  
   var listvalues = { "amt": amountTendered, "pType": paymentOpt }
   localStorage.setItem('lists', JSON.stringify(listvalues)); 
   jpost.post("addsale.php", { submitSale : true, paymentType : paymentOpt, amountTendered : amountTendered })
       .done(function(data){ console.log(data);
	   setTimeout(function() {
  	     window.location = "addsale.php";
	   }, 3000);     
	});
});*/
jpost('.add-sale').click(function(){
    jpost('#sale').submit();
});
jpost('.clear-sale').click(function(){
  jpost.post("sale_ui.php", { remove: true})
       .done(function(data){
          console.log(data);
          window.location.reload();
       });
});
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
<script>
jQuery('<div class="quantity-nav"><div class="quantity-button quantity-up">+</div><div class="quantity-button quantity-down">-</div></div>').insertAfter('.quantity input');
    jQuery('.quantity').each(function() {
      var spinner = jQuery(this),
        input = spinner.find('input[type="number"]'),
        btnUp = spinner.find('.quantity-up'),
        btnDown = spinner.find('.quantity-down'),
        min = input.attr('min'),
        max = input.attr('max');

      btnUp.click(function() {
        var oldValue = parseFloat(input.val());
        if (oldValue >= max) {
          var newVal = oldValue;
        } else {
          var newVal = oldValue + 1;
        }
        spinner.find("input").val(newVal);
        spinner.find("input").trigger("change");
      });

      btnDown.click(function() {
        var oldValue = parseFloat(input.val());
        if (oldValue <= min) {
          var newVal = oldValue;
        } else {
          var newVal = oldValue - 1;
        }
        spinner.find("input").val(newVal);
        spinner.find("input").trigger("change");
      });

    });
</script>

<!-- modal dialog -->
<div class="modal fade" id="addnewcustomer" tabindex="-1" role="dialog" aria-labelledby="addnewcustomerLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <?php include ("../classes/form.php"); ?>
      <div class="modal-header" style="background:#e4388c;">
        <h5 class="modal-title" id="addnewcustomerLabel"><?php $display->displayTitle("$lang->addCustomer"); ?></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
		<span aria-hidden="true">&times;</span>
	</button>
      </div>
      <div class="modal-body">

      <!-- Main Content -->
      <div id="content">
      <!-- Begin Page Content -->
      <div class="pagebody">
	 <div class="container-fluid">

	<?php //set default values, these will change if $action==update.
	  $first_name_value=''; $last_name_value=''; $account_number_value=''; $phone_number_value=''; 
	  $email_value=''; $street_address_value=''; $comments_value=''; $id=-1; $action="insert"; ?>
	<?php //creates a form object
	$f1=new form('process_form_customers.php','POST','customers','450',$cfg_theme,$lang); //creates form parts.
	$f1->createInputField_cust("<b>$lang->firstName:</b> ",'text','customers_firstname',"$first_name_value",'24','150');
	$f1->createInputField_cust("<b>$lang->lastName:</b> ",'text','customers_lastname',"$last_name_value",'24','150');
	//$f1->createInputField_cust("<b>$lang->accountNumber:</b> ",'text','customers_account_number',"$account_number_value",'24','150');
	$f1->createInputField_cust("<b>$lang->phoneNumber</b> ",'text','customers_telephone',"$phone_number_value",'24','150');
	$f1->createInputField_cust("<b>$lang->email:</b> ",'text','customers_email_address',"$email_value",'24','150');
	//$f1->createInputField_cust("<b>$lang->streetAddress:</b> ",'text','customers_default_address_id',"$street_address_value",'24','150');
	//$f1->createInputField_cust("<b>$lang->commentsOrOther:</b> ",'text','customers_comments',"$comments_value",'40','150');

	//sends 2 hidden varibles needed for process_form_users.php.
	echo "<div class='hidden'><input type='hidden' name='action' value='$action'/><input type='hidden' name='id' value='$id'/></div>";
	//$f1->endForm_cust(); ?>
	<div class="form-group row justify-content-center text-center">
	  <div class="col-6">
           <button type="button" class="themegradirn-bg text-white text-uppercase btn" style="margin-top:10px;">SUBMIT</button>
	  </div>
	</div>
	</form>

	</div><!-- /.container-fluid -->
       </div>        
       </div><!-- End of Main Content -->
      </div>
      <div class="modal-footer"></div>

     <script>
      $jqc = jQuery.noConflict(); 
      $jqc('.modal-body .btn').click(function(){ 
        var fname='', lname='', phno='', email='';   
	var action='', id='';
	$jqc('.modal-body input[type="text"]').each(function(){ 
           if($jqc(this).attr('name') == 'customers_firstname'){ fname = $jqc(this).val(); }
           if($jqc(this).attr('name') == 'customers_lastname'){ lname = $jqc(this).val(); }
           if($jqc(this).attr('name') == 'customers_telephone'){ phno = $jqc(this).val(); }
           if($jqc(this).attr('name') == 'customers_email_address'){ email = $jqc(this).val(); }
	}); //console.log(fname + lname + phno + email);
	$jqc('.modal-body input[type="hidden"]').each(function(){
	   if($jqc(this).attr('name') == 'action'){ action = $jqc(this).val(); }
	   if($jqc(this).attr('name') == 'id'){ id = $jqc(this).val(); } 	
	}); 
	$jqc.post("http://xsales.com.au/POS/"+"customers/process_form_customers.php",
		   {'customers_firstname':fname,'customers_lastname':lname,'customers_telephone':phno,
			'customers_email_address':email,'action':action,'id':id })
	    .done(function(data){ //console.log(data);
		//console.log($jqc(data).find('.container-fluid').text());
		htmldata = $jqc(data).find('.container-fluid').html(); console.log(htmldata);
		if($jqc(htmldata).find('table').length > 0){
		  htmlmod = '<p style="color:#060682;">'+'Customer Details Added Successfully'+'</p>'; 
		  //$jqc(htmldata).find('center').html(); console.log(htmlmod); 
		}else{ htmlmod = htmldata; } 
		$jqc('.modal-footer').css('color','#060682');
		$jqc('.modal-footer').html(htmlmod);
	    });
      });
     </script>

    </div>
  </div>
</div>
<?php $dbf->closeDBlink(); ?>
