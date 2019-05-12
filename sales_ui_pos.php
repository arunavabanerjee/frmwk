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
if(isset($_POST['addnewcustomer_email'])){
   $customers_table="$cfg_tableprefix".'customers';
   if($_POST['addnewcustomer_email'] != ''){
      $addnewqry = 'SELECT * FROM '.$customers_table.' WHERE customers_email_address = "'.$_POST['addnewcustomer_email'].'"'; 
      $row = mysql_fetch_row(mysql_query($addnewqry, $dbf->conn));
      if(!isset($_SESSION['current_sale_customer_id'])){
         $_SESSION['current_sale_customer_id'] = $row[0]; //echo $_SESSION['current_sale_customer_id'];
      } else {
	$_SESSION['current_sale_customer_id'] = $row[0];
      }
      $html = '<p style="color:#060682;">Added Customer Set For POS Sale</p>';
      //$html .= '<meta http-equiv="refresh" content="2;url='.$_SERVER["HTTP_REFERER"].'" />';
      echo $html; die();
   }
}

if(empty($_SESSION['current_sale_customer_id']) 
	&& !isset($_POST['customer_search']) && !isset($_POST['customer'])){
  //loads the first customer, that is guest customer by default
  $_SESSION['current_sale_customer_id'] = 1; 
}

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
if(empty($_POST['item_search_pname']) && empty($_POST['item_search_pmpn']) 
	&& empty($_POST['item_search_pbcode']) && empty($_POST['item_search_pman']) ){ 
	$_SESSION['current_item_from_table']=''; $_SESSION['current_item_search']=''; 
}

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
	/*$query .= " WHERE p.products_status = '1' and p.products_quantity > 0 and p.products_id=pd.products_id and
			p.products_tax_class_id=tc.tax_class_id and tr.tax_class_id=tc.tax_class_id "; */
	$query .= " WHERE p.products_quantity > 0 and p.products_id=pd.products_id and
			p.products_tax_class_id=tc.tax_class_id and tr.tax_class_id=tc.tax_class_id "; 
	$query .= $search; 
	$query .= " and pd.language_id=1 ORDER by pd.products_name";
  
} elseif(isset($_SESSION['current_item_search']) && $_SESSION['current_item_search'] != "") { 
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
	/*$query .= " WHERE p.products_status = '1' and p.products_quantity > 0 and p.products_id=pd.products_id and
			p.products_tax_class_id=tc.tax_class_id and tr.tax_class_id=tc.tax_class_id "; */
	$query .= " WHERE p.products_quantity > 0 and p.products_id=pd.products_id and
			p.products_tax_class_id=tc.tax_class_id and tr.tax_class_id=tc.tax_class_id "; 

	$query .= $search;
	$query .= " and pd.language_id=1 ORDER by pd.products_name"; //echo $query;

} else {
	//sale price added
  	/*$query="SELECT p.products_id,pd.products_name,p.products_price,tr.tax_rate, 
		IF(s.status, s.specials_new_products_price, NULL) as specials_new_products_price, 
		IF(s.status, s.specials_new_products_price, p.products_price) as final_price
		FROM products_description as pd,products p left join specials s on p.products_id = s.products_id,tax_rates as tr,tax_class as tc
		WHERE p.products_status = '1' and p.products_quantity > 0 and p.products_id=pd.products_id and
			p.products_tax_class_id=tc.tax_class_id and tr.tax_class_id=tc.tax_class_id and pd.language_id=1 
		ORDER by pd.products_name";*/
	$query="SELECT p.products_id,pd.products_name,p.products_price,tr.tax_rate, 
		IF(s.status, s.specials_new_products_price, NULL) as specials_new_products_price, 
		IF(s.status, s.specials_new_products_price, p.products_price) as final_price
		FROM products_description as pd,products p left join specials s on p.products_id = s.products_id,tax_rates as tr,tax_class as tc
		WHERE p.products_quantity > 0 and p.products_id=pd.products_id and p.products_tax_class_id=tc.tax_class_id and 
			tr.tax_class_id=tc.tax_class_id and pd.language_id=1 
		ORDER by pd.products_name";
}
$item_result=mysql_query($query,$dbf_osc->conn); 
//$count_items = mysql_num_rows($item_result);
$count_items = 0; $i=0; $item_data_1 = '<ul class="searpro__result__list__dtls" name="items" id="accordion">';
while($row=mysql_fetch_assoc($item_result)){
	$id=$row['products_id']; 
        $query = "SELECT pa.products_id,pa.products_attributes_id,pa.options_id,pa.options_values_id, 
		  pa.options_values_price,pa.quantity,po.products_options_name,pov.products_options_values_name
		  FROM products_attributes pa 
		  RIGHT JOIN products_options po ON pa.options_id = po.products_options_id 
		  LEFT JOIN products_options_values pov ON pa.options_values_id = pov.products_options_values_id
		  WHERE products_id='".$id."' AND po.products_options_name like '%-Shop'
		  ORDER BY po.products_options_name ASC";
	$attr_result=mysql_query($query,$dbf_osc->conn); $count_aitems = mysql_num_rows($attr_result);

  	//sale price added
	if ($row['specials_new_products_price']){
		$unit_price=$row['specials_new_products_price'];
	} else{ $unit_price=$row['products_price']; }
  	$tax_percent=$row['tax_rate'];
  	//$option_value="$id"."/"."$unit_price"."/"."$tax_percent";
	$option_value="$id"; $display_item="$row[products_name]"; 

	//total number of items
        //$total_items = 0; while($attrrow=mysql_fetch_assoc($attr_result)){ $total_items += $attrrow['quantity']; }
	//$display_item .= " - $total_items"; 

	if(!empty($count_aitems)){
	  if($count_aitems == 1){ //if count aitems == 1 
		$attrrow=mysql_fetch_assoc($attr_result);
		//if product have no options
		if(strstr($attrrow['products_options_name'], "NoAttributes-Shop")){
                   $noattr_option_value=$attrrow['products_id'].'-'.$attrrow['products_attributes_id'].'-'.$attrrow['options_id'].
					'-'.$attrrow['options_values_id'];
		   $item_data_1 .= "<li value='$noattr_option_value'><a href='javascript:void(0)'>$display_item</a></li>";		
		} else { 
		   $item_data_1 .= "<li value='$option_value' class='clickdisabled'>";
		   $item_data_1 .= "<a data-toggle='collapse' aria-expanded='false' href='#listcat".$i."'>$display_item"; 
		   $item_data_1 .= "<span class='collaspeicons float-right'></span></a>";
		   $item_data_1 .= "<div class='collapse' id='listcat".$i."' data-parent='#accordion'>";

		   $item_data_1 .= "<h6 style='font-size:14px;font-weight:bold;background:#dcdbdb;margin:0;border-bottom:1px solid #AAA;padding-left:10px;'>".$attrrow['products_options_name']."</h6>";
		   $item_data_1 .= "<ul>";
		   $attr_option_value = $attrrow['products_id'].'-'.$attrrow['products_attributes_id'].
					'-'.$attrrow['options_id'].'-'.$attrrow['options_values_id'];
		   $item_data_1 .= "<li value=".$attr_option_value.">".$attrrow['products_options_values_name']."</li>";
		   $item_data_1 .= "</ul></li>"; $i++;	
		}
	  } else{ //if count aitems > 1 
		$item_data_1 .= "<li value='$option_value' class='clickdisabled'>";
		$item_data_1 .= "<a data-toggle='collapse' aria-expanded='false' href='#listcat".$i."'>$display_item"; 
		$item_data_1 .= "<span class='collaspeicons float-right'></span></a>";
		$item_data_1 .= "<div class='collapse' id='listcat".$i."' data-parent='#accordion'>";
		$attributes = array(); $cnt=0; 
		while($attrrow=mysql_fetch_assoc($attr_result)){
		  if(!in_array($attrrow['products_options_name'], $attributes)){ 
		     if($cnt > 0){ $item_data_1 .= "</ul>"; }
		     $cnt=0; array_push($attributes, $attrrow['products_options_name']); 
		  }	
		  if($cnt == 0){ 
		    $item_data_1 .= "<h6 style='font-size:14px;font-weight:bold;background:#dcdbdb;margin:0;border-bottom:1px solid #AAA;padding-left:10px;'>".$attrrow['products_options_name']."</h6>";
		    $item_data_1 .= "<ul>"; 
		    $attr_option_value=$attrrow['products_id'].'-'.$attrrow['products_attributes_id'].'-'.$attrrow['options_id'].
					'-'.$attrrow['options_values_id'];
		    $item_data_1 .= "<li value=".$attr_option_value.">".$attrrow['products_options_values_name']."</li>";
		    $cnt++;
		  } else {
		    $attr_option_value=$attrrow['products_id'].'-'.$attrrow['products_attributes_id'].'-'.$attrrow['options_id'].
					'-'.$attrrow['options_values_id'];
		    $item_data_1 .= "<li value=".$attr_option_value.">".$attrrow['products_options_values_name']."</li>";
		    $cnt++;
		  }
	       }//end while 
	      if($cnt > 0){ $item_data_1 .= "</ul>"; } // end remaining ul	
	      $item_data_1 .= '</div></li>'; $i++; // increment accordian counter	
	  } // end if-else
	$count_items += $count_aitems;
	}//end if(!empty($count_aitems))
} //end outer while
$item_data_1 .= "</ul>";
$item_title = isset($_SESSION['current_item_search']) ? "<font class='smalltitle themecolor'>$lang->selectItem ($count_items)</font>":"<font class='smalltitle themecolor'>$lang->selectItem ($count_items)</font>";
$item_data = '<h4 class="smalltitle themecolor"> Result: '.$item_title.'</h4>';
$item_data .= $item_data_1;

/**
 * add to cart items
 * called via jquery post
 */
if(isset($_POST['pid'])){ //echo $_POST['pid']; 
  $sale_items = isset($_SESSION['items_in_sale'])? $_SESSION['items_in_sale'] : "" ; 
  if(!isset($_SESSION['current_sale_customer_id']) && empty($sale_items)){ 
     $html = '<tr><td colspan="6">';
     $html .= '<p style="color:red;font-weight:bold">Need To Select Customer First To Start A Sale</p>';
     $html .= '</td></tr>';
  } else { //start a sale 
    if($_POST['pid'] == 0){ 
        if(!isset($_SESSION['not_found_items'])){
	   $pid = strval(1000001); $notFoundItems = 1; $_SESSION['not_found_items'] = $notFoundItems;	
        } else{ 
	   $count = $_SESSION['not_found_items']; $pid = strval(1000001 + $count); $_SESSION['not_found_items'] = ++$count;  } 
	$pname = $_POST['p_name']; $pquant = $_POST['p_quant']; 
	$pgross = $_POST['p_gross']; $pcomments = $_POST['p_comments']; 
	$sale_items .= $pid.','.$pname.'/'.$pcomments.','.'0.00'.','.'0'.','.$pquant.','.$pgross.';;';
	$_SESSION['items_in_sale'] = $sale_items;
	//append values
    	$html = '<tr id="'.$pid.'">';
    	$html .= '<td class="pronamedtls"><span class="d-block">'.$pname.'/'.$pcomments.'</span></td>';
    	$html .= '<td class="uprice">'.number_format(floatval(0),2).'</td><td class="taxpc">'.number_format(floatval(0),0).'</td>';
    	$html .= '<td><div class="quantity"><input type="number" min="1" max="9" step="1" value="'.$pquant.'"></div></td>';
    	$html .= '<td class="totalprice">'.number_format(floatval($pgross),2,'.','').'</td>';
    	$html .= '<td class="deletepro"><i class="lnr lnr-cross-circle"></i></td>';
    	$html .= '</tr>';
    } else{
        if(isset($_POST['attrid']) && isset($_POST['optid']) && isset($_POST['optvalid'])){ 
	  $query = "SELECT pa.products_id,pa.products_attributes_id,pa.options_id,pa.options_values_id, 
		  pa.options_values_price,pa.quantity,po.products_options_name,pov.products_options_values_name
		  FROM products_attributes pa 
		  RIGHT JOIN products_options po ON pa.options_id = po.products_options_id 
		  LEFT JOIN products_options_values pov ON pa.options_values_id = pov.products_options_values_id
		  WHERE products_id='".$_POST['pid']."' AND pa.products_attributes_id='".$_POST['attrid']."' 
			AND pa.options_id='".$_POST['optid']."' AND pa.options_values_id='".$_POST['optvalid']."'";	
	  $attr_result=mysql_fetch_assoc(mysql_query($query,$dbf_osc->conn));
	}
        //query for product values 
        $tQuery = 'SELECT tax_rate FROM tax_rates tr, products p 
		  WHERE p.products_tax_class_id=tr.tax_rates_id and p.products_id="'.$_POST['pid'].'"'; 
        $tResult = mysql_fetch_row(mysql_query($tQuery,$dbf_osc->conn));  
	$pQuery = "SELECT p.products_price, pd.products_name FROM products as p,products_description as pd 
		WHERE p.products_id=pd.products_id and p.products_id='".$_POST['pid']."'";  
    	$pResult = mysql_fetch_row(mysql_query($pQuery,$dbf_osc->conn)); 
        $uprice = number_format(floatval($pResult[0]),2,'.','');
	//add the attribute price
	if(isset($_POST['attrid']) && isset($_POST['optid']) && isset($_POST['optvalid'])){
    	  $uprice += number_format($attr_result['options_values_price'],2,'.','');
	}
    	$retailtaxpc = number_format(floatval($tResult[0]),0);
    	$retailprice = number_format(floatval($uprice + (($retailtaxpc * $uprice)/100)),2,'.',''); 
	$pquant = 1; $key = ''; 
	if(isset($_POST['attrid']) && isset($_POST['optid']) && isset($_POST['optvalid'])){
	  $key = $_POST['pid'].'-'.$_POST['attrid'].'-'.$_POST['optid'].'-'.$_POST['optvalid'];
          $sale_items .= $key.','.$pResult[1].','.$uprice.','.$retailtaxpc.','.$pquant.','.$retailprice.';;';
	} else{ 
          $key = $_POST['pid'];    
	  $sale_items .= $key.','.$pResult[1].','.$uprice.','.$retailtaxpc.','.$pquant.','.$retailprice.';;';
        }
        $_SESSION['items_in_sale'] = $sale_items; 
	//append values
    	$html = '<tr id="'.$key.'">';
    	$html .= '<td class="pronamedtls"><span class="d-block">'.$pResult[1].'</span>'; 
	if(isset($_POST['attrid']) && isset($_POST['optid']) && isset($_POST['optvalid'])){
    	  $html .= '<span class="d-block">'.$attr_result['products_options_name'].' : '.$attr_result['products_options_values_name'].'</span></td>';
	}
    	$html .= '</td><td class="uprice">'.$uprice.'</td><td class="taxpc">'.$retailtaxpc.'</td>';
    	$html .= '<td><div class="quantity"><input type="number" min="1" max="9" step="1" value="1"></div></td>';
    	$html .= '<td class="totalprice">'.$retailprice.'</td>';
    	$html .= '<td class="deletepro"><i class="lnr lnr-cross-circle"></i></td>';
    	$html .= '</tr>';
    }
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
  $sale_items_arr = explode(';;',$sale_items); 
  $sale_items_narr = array();
  foreach($sale_items_arr as $sale_item){ 
     if(empty($sale_item)){ continue; }
     if(strstr($sale_item,$_POST['pdelid'])){ continue; }
     array_push($sale_items_narr,$sale_item);
  }
  $sale_items = implode(';;',$sale_items_narr); $sale_items = $sale_items.';;'; 
  if($sale_items == ';;'){ $sale_items = ''; }
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
   if(isset($_POST['subtotal']) && !empty($_POST['subtotal'])){ $_SESSION['subtotal'] = $_POST['subtotal']; }
   if(isset($_POST['taxtotal']) && !empty($_POST['taxtotal'])){ $_SESSION['taxtotal'] =  $_POST['taxtotal']; }
   if(isset($_POST['grandtotal']) && !empty($_POST['grandtotal'])){ $_SESSION['grandtotal'] = $_POST['grandtotal']; }
   if(isset($_POST['discounted']) && !empty($_POST['discounted'])){ $_SESSION['disc_grandtotal'] = $_POST['discounted']; }
   if(isset($_POST['discount']) && !empty($_POST['discount'])){ $_SESSION['pcdiscount'] = $_POST['discount']; }
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
   unset($_SESSION["not_found_items"]);
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
  $item_info=explode(';;', $_SESSION['items_in_sale']); 
  $num_items=count($item_info); $carthtml = ''; 
  $sub_total = 0.00; $tax_total = 0.00; $grand_total = 0.00;

  foreach($item_info as $item){ if(empty($item)){ continue; }
    $itemDet = explode(',', $item); //var_dump($itemDet);
    if(strstr($itemDet[0], '-')){
      $itemIds = explode('-', $itemDet[0]);
      $query = "SELECT pa.products_id,pa.products_attributes_id,pa.options_id,pa.options_values_id, 
		  pa.options_values_price,pa.quantity,po.products_options_name,pov.products_options_values_name
		  FROM products_attributes pa 
		  RIGHT JOIN products_options po ON pa.options_id = po.products_options_id 
		  LEFT JOIN products_options_values pov ON pa.options_values_id = pov.products_options_values_id
		  WHERE products_id='".$itemIds[0]."' AND pa.products_attributes_id='".$itemIds[1]."' 
			AND pa.options_id='".$itemIds[2]."' AND pa.options_values_id='".$itemIds[3]."'";	
      $attr_result=mysql_fetch_assoc(mysql_query($query,$dbf_osc->conn));
    }
    $retailtax = number_format(floatval(($itemDet[2] * $itemDet[3])/100), 2, '.', ''); 
    //cart html 
    $carthtml .= '<tr id="'.$itemDet[0].'">';
    $carthtml .= '<td class="pronamedtls"><span class="d-block">'.$itemDet[1].'</span>'; 
    if(strstr($itemDet[0], '-')){
      $carthtml .= '<span class="d-block">'.$attr_result['products_options_name'].' : '.$attr_result['products_options_values_name'].'</span>';
    }
    $carthtml .= '</td><td class="uprice">'.$itemDet[2].'</td><td class="taxpc">'.$itemDet[3].'</td>';
    $carthtml .= '<td><div class="quantity"><input type="number" min="1" max="9" step="1" value="'.$itemDet[4].'"></div></td>';
    $carthtml .= '<td class="totalprice">'.$itemDet[5].'</td>';
    $carthtml .= '<td class="deletepro"><i class="lnr lnr-cross-circle"></i></td>';
    $carthtml .= '</tr>'; 
    $sub_total += $itemDet[2]; $tax_total += $retailtax; $grand_total += $itemDet[5];
  }
  $sub_total = number_format(floatval($sub_total), 2, '.', '');
  $tax_total = number_format(floatval($tax_total), 2, '.', '');  
  $grand_total = number_format(floatval($grand_total), 2, '.', ''); 
}

var_dump($_SESSION); var_dump($_POST);
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

		<!-- discount segment -->
		<tr id="discount_field">
		    <td class="text-right text-sucess font-weight-bold">Discount</td>
		    <td class="text-left text-sucess sales-tax themecolor font-weight-bold">
			<span class="lnr lnr-plus-circle" id="showcoupon"></span>
		    </td>
		</tr>
		<tr id="coponcode_field" class="d-none">
		    <td colspan="2" class="text-right text-sucess font-weight-bold">
		    <div class="form-flex">
                    <div class="input-group">
		      <input type="text" class="form-control" id="promocodeval" placeholder="% Discount" 
				onkeyup="if (/\D/g.test(this.value)) this.value = this.value.replace(/\D/g,'')" />
		      <div class="input-group-append"><button type="button" class="input-group-text" id="applycc">Apply</button></div>
		    </div>
                    </div>
		    </td>
		</tr>
		<!-- end discount segment -->


		<tr>
			<td class="text-right themecolor font-weight-bold">Discounted Total: AUD($)</td>
			<td class="text-left themecolor font-weight-bold discounted-total">
			<?php if(!empty($_SESSION['items_in_sale'])){ echo 0.00; } ?>
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
		  <button type="button" class="btn themegradirn-bg text-white text-uppercase" style="margin-bottom:5px"onclick="jQuery('#finditem').submit();">Find Product</button>
		  <button type="button" class="btn themegradirn-bg text-white text-uppercase" style="margin-bottom:5px" onclick="jQuery('#finditem').submit();">Reset</button>
		  <button type="button" class="btn themegradirn-bg text-white text-uppercase" style="margin-bottom:5px" data-toggle="modal" data-target="#addproductforsale"">Add Not Found Products</button>
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
jpost(document).ready(function(){ 
  jpost('.searpro__result__list__dtls li ul').each(function(){ 
     jpost(this).css('padding',"0px");
     jpost(this).find('li').each(function(){ 
	jpost(this).css({ "background": "#dcdbdb","font-size": "14px","padding-left":"30px","font-weight":"bold",
			  "color":"#000000","border-bottom":"1px solid #BBB","text-transform":"capitalize",
			  "cursor":"pointer" });
     });
  });
});
jpost('.searpro__result__list__dtls li').click(function(){
  var name = jpost(this).text(); //console.log(name);
  var value = jpost(this).val(); //console.log(value); 
  var value1 = jpost(this).attr('value'); //console.log(value1);
  if(jpost(this).hasClass('clickdisabled')){ return true; }
  if( value1 != value ){ 
   if( value1.indexOf('-') > 0 ){
     var pids = value1.split('-');
     jpost.post("sale_ui.php", { pid:pids[0], attrid:pids[1], optid:pids[2], optvalid:pids[3] })
          .done(function(data){
             if( value > 0){ jpost('.searchtable center').css('display', 'none'); }
             jpost('.search__table tbody').append(data);
             //make calculations and add to grand total 
	     var uprice = jpost(data).find('td.uprice').text(); //console.log(uprice); 
	     var taxpc = jpost(data).find('td.taxpc').text(); //console.log(taxpc);
	     var quant = jpost(data).find('input[type="number"]').val(); //console.log(quant);
	     var price = jpost(data).find('td.totalprice').text(); //console.log(price);
	     //cals tax
	     var prevtaxretail = parseFloat(jpost('.sels__stab__comnt_right .sales-tax').text()); 
             var taxretail = (parseFloat(taxpc) * parseFloat(uprice)) / 100;
	     var taxretailn = taxretail + prevtaxretail; 
	     jpost('.sels__stab__comnt_right .sales-tax').text(taxretailn.toFixed(2)); 
	     //cals subtotal
	     var prevsubtotalretail = parseFloat(jpost('.sels__stab__comnt_right .sub-total').text()); 
	     var subtotalretail = parseFloat(uprice); 
	     var subtotalretailn = subtotalretail + prevsubtotalretail;   
             jpost('.sels__stab__comnt_right .sub-total').text(subtotalretailn.toFixed(2));
	     //cals grandtotal
	     var prevgrandtotalretail = parseFloat(jpost('.sels__stab__comnt_right .grand-total').text());
	     var grandtotalretail = parseFloat(price);
	     var grandtotalretailn = grandtotalretail + prevgrandtotalretail; 
             jpost('.sels__stab__comnt_right .grand-total').text(grandtotalretailn.toFixed(2));
	     jpost('#amount-tendered').val(grandtotalretailn.toFixed(2));
	     jpost.post("sale_ui.php", { updtses:true, grandtotal:grandtotalretailn, 
					subtotal:subtotalretailn, taxtotal:taxretailn })
	          .done(function(data){ console.log(data); });
   	});
    } 
  } else { 
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
	   //cals tax
	   var prevtaxretail = parseFloat(jpost('.sels__stab__comnt_right .sales-tax').text()); 
           var taxretail = (parseFloat(taxpc) * parseFloat(uprice)) / 100;
	   var taxretailn = taxretail + prevtaxretail; 
	   jpost('.sels__stab__comnt_right .sales-tax').text(taxretailn.toFixed(2)); 
	   //cals subtotal
	   var prevsubtotalretail = parseFloat(jpost('.sels__stab__comnt_right .sub-total').text()); 
	   var subtotalretail = parseFloat(uprice); 
	   var subtotalretailn = subtotalretail + prevsubtotalretail;   
           jpost('.sels__stab__comnt_right .sub-total').text(subtotalretailn.toFixed(2));
	   //cals grandtotal
	   var prevgrandtotalretail = parseFloat(jpost('.sels__stab__comnt_right .grand-total').text());
	   var grandtotalretail = parseFloat(price);
	   var grandtotalretailn = grandtotalretail + prevgrandtotalretail; 
           jpost('.sels__stab__comnt_right .grand-total').text(grandtotalretailn.toFixed(2));
	   jpost('#amount-tendered').val(grandtotalretailn.toFixed(2));
	   jpost.post("sale_ui.php", { updtses:true, grandtotal:grandtotalretailn, 
					subtotal:subtotalretailn, taxtotal:taxretailn })
	   	.done(function(data){ console.log(data); });
   	}); 
   } //end else 
});
jpost(document).on("click", '.search__table .deletepro', function(event){ 
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
	   setTimeout(function(){ jpost(".pos__search__result .messages").fadeOut().empty(); }, 3000);
	   //cals tax
      	   var taxretail = (parseFloat(taxpc) * parseFloat(uprice)) / 100;
	   var prevtaxretail = parseFloat(jpost('.sels__stab__comnt_right .sales-tax').text()); 
	   var taxretailn = prevtaxretail - taxretail; 
	   if(taxretailn < 0){ taxretailn = 0.00; }
           jpost('.sels__stab__comnt_right .sales-tax').text(taxretailn.toFixed(2));
	   //cals subtotal
	   var subtotalretail = parseFloat(uprice);
	   var prevsubtotalretail = parseFloat(jpost('.sels__stab__comnt_right .sub-total').text()); 
	   var subtotalretailn = prevsubtotalretail - subtotalretail; 
           if(subtotalretailn < 0){ subtotalretailn = 0.00; }
           jpost('.sels__stab__comnt_right .sub-total').text(subtotalretailn.toFixed(2));	  
	   //cals grandtotal
      	   var grandtotalretail = parseFloat(price);
	   var prevgrandtotalretail = parseFloat(jpost('.sels__stab__comnt_right .grand-total').text()); 
	   var grandtotalretailn = prevgrandtotalretail - grandtotalretail; 
	   if(grandtotalretailn < 0){ grandtotalretailn = 0.00; }
           jpost('.sels__stab__comnt_right .grand-total').text(grandtotalretailn.toFixed(2)); 	
	   jpost('#amount-tendered').val(grandtotalretailn.toFixed(2)); 
	   //updt session
	   jpost.post("sale_ui.php", { updtses:true, grandtotal:grandtotalretailn, 
					subtotal:subtotalretailn, taxtotal:taxretailn })
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
	   setTimeout(function() { window.location = "addsale.php"; }, 3000);     
	});
});*/
jpost('.add-sale').click(function(){
    jpost('#sale').submit();
});
jpost('.clear-sale').click(function(){
  jpost.post("sale_ui.php", { remove: true})
       .done(function(data){ console.log(data); window.location.reload(); });
});
jpost('#applycc').click(function(){
  var curr_subtotalretail = parseFloat(jpost('.sels__stab__comnt_right .sub-total').text()); //console.log(curr_subtotalretail);
  var pcdiscount = parseInt(jpost('#promocodeval').val()); //console.log(pcdiscount); 
  var applydiscount = curr_subtotalretail - ((pcdiscount * curr_subtotalretail)/100); //console.log(applydiscount);
  var curr_taxtotalretail = parseFloat(jpost('.sels__stab__comnt_right .sales-tax').text()); //console.log(curr_taxtotalretail);
  var disc_grandtotalretail = curr_taxtotalretail + applydiscount; //console.log(disc_grandtotalretail);
  //update session  
  jpost('.sels__stab__comnt_right .discounted-total').text(disc_grandtotalretail.toFixed(2)); 
  jpost('.sels__stab__comnt_right .grand-total').css({ "text-decoration":"line-through", "color":"#AAA" }); 
  jpost('#amount-tendered').val(disc_grandtotalretail.toFixed(2));
  jpost.post("sale_ui.php", { updtses:true, discounted:disc_grandtotalretail, discount:pcdiscount })
       .done(function(data){ console.log(data); });
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
jQuery(document).ready(function(e){ 
	jQuery("#showcoupon").click(function(){
		jQuery("#showcoupon").toggleClass('roatedicon');
		jQuery("#coponcode_field").toggleClass("d-none");
		jQuery('#promocodeval').focus();
	});
	jQuery("#applycc").click(function(){
		var discount=jQuery("#promocodeval").val();
		if(discount !=''){
			var discount1=discount;
		}else{
			var discount1="0";
		}
		jQuery("#coponcode_field").addClass('d-none');
		jQuery("#showcoupon").removeClass("lnr lnr-plus-circle roatedicon").html(discount1+"<span class='ml-1' id='changecc'>[Change]</span>").attr('id', 'nscoupan');
		jQuery("#nscoupan").html(discount1+"<span class='ml-1' id='changecc'>[Change]</span>");
		jQuery('#promocodeval').focus();
	});  
});
</script>
<script>
/*jQuery('<div class="quantity-nav"><div class="quantity-button quantity-up">+</div><div class="quantity-button quantity-down">-</div></div>').insertAfter('.quantity input');
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

    });*/
</script>

<!-- modal dialog -->
<div class="modal fade" id="addnewcustomer" tabindex="-1" role="dialog" aria-labelledby="addnewcustomerLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <?php include_once("../classes/form.php"); ?>
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
        var ajaxurl1 = "<?php echo $cfg_website; ?>" + 'customers/process_form_customers.php';
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
	$jqc.post( ajaxurl1, {'customers_firstname':fname,'customers_lastname':lname,'customers_telephone':phno,
				'customers_email_address':email,'action':action,'id':id })
	    .done(function(data){ //console.log(data);
		//console.log($jqc(data).find('.container-fluid').text());
		htmldata = $jqc(data).find('.container-fluid').html(); console.log(htmldata);
		if($jqc(htmldata).find('table').length > 0){
		  htmlmod = '<p style="color:#060682;">'+'Customer Details Added Successfully'+'</p>'; 
		  //set the added customer as customer for sale
		  $jqc.post("sale_ui.php",{ 'addnewcustomer_email':email })
		      .done(function(data){ $jqc('.modal-footer').html(data); /*window.location.reload();*/ });
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

<!-- modal dialog -->
<div class="modal fade" id="addproductforsale" tabindex="-1" role="dialog" aria-labelledby="addproductforsaleLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <?php include_once("../classes/form.php"); ?>
      <div class="modal-header" style="background:#e4388c;">
        <h5 class="modal-title" id="addproductforsaleLabel"><?php $display->displayTitle("ADD PRODUCT NOT FOUND"); ?></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">

      <!-- Main Content -->
      <div id="content">
      <!-- Begin Page Content -->
      <div class="pagebody">
	 <div class="container-fluid">

	<?php //set default values, these will change if $action==update.
	 $product_name_value='NEW-ITEM-ADD-TO-DB'; $product_quantity_value=''; $product_gross_total=''; $product_comments='';  ?>
	<?php //creates a form object
	 $f1=new form('sale_ui.php','POST','addproductforsaleform','850',$cfg_theme,$lang); //creates form parts.
	 $f1->createInputField_New("<b>Product Name:</b> ",'text','product_name',"$product_name_value",'24','150');
	 $f1->createInputField_New("<b>Quantity:</b> ",'text','product_quantity',"$product_quantity_value",'24','150');
	 $f1->createInputField_New("<b>Gross Total:</b> ",'text','product_gross_total',"$product_gross_total",'24','150');	
	 $f1->createTextareaField_New("<b>Comments:</b> ",'product_comments',4,18,"$product_comments",'150'); ?>
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
      $jqObj = jQuery.noConflict(); 
      $jqObj('#addproductforsale').on('shown.bs.modal', function(){ 
	$jqObj('#addproductforsale .modal-body input[type="text"]').each(function(){ 
           if($jqObj(this).attr('name') == 'product_name'){ $jqObj(this).css('width', '121%'); }
           if($jqObj(this).attr('name') == 'product_quantity'){ $jqObj(this).css('width', '121%'); }
           if($jqObj(this).attr('name') == 'product_gross_total'){ $jqObj(this).css('width', '121%'); }
	}); 
	$jqObj('#addproductforsale .modal-body textarea').each(function(){ 
           if($jqObj(this).attr('name') == 'product_comments'){ $jqObj(this).css('width', '121%'); }
	}); //console.log(pname + pquantity + pgrosstotal + pcomments); 
      });	

      $jqObj('#addproductforsale .modal-body .btn').click(function(){ 
        //var ajaxurl2 = "<?php echo $cfg_website; ?>" + 'sales/sale_ui.php';
        var pname='', pquantity='', pgrosstotal='', pcomments='';
	$jqObj('#addproductforsale .modal-body input[type="text"]').each(function(){ 
           if($jqObj(this).attr('name') == 'product_name'){ pname = $jqObj(this).val(); }
           if($jqObj(this).attr('name') == 'product_quantity'){ pquantity = $jqObj(this).val(); }
           if($jqObj(this).attr('name') == 'product_gross_total'){ pgrosstotal = $jqObj(this).val(); }
	}); 
	$jqObj('#addproductforsale .modal-body textarea').each(function(){ 
           if($jqObj(this).attr('name') == 'product_comments'){ pcomments = $jqObj(this).val(); }
	}); //console.log(pname + pquantity + pgrosstotal + pcomments);
 
	//create the product and append to cart 
	$jqObj.post("sale_ui.php", {'pid':0,'p_name':pname,'p_quant':pquantity,'p_gross':pgrosstotal,'p_comments':pcomments })
	    .done(function(data){ //console.log(data); 
           	if($jqObj(data).closest('tr').attr('id') > 0){ 
	     	   $jqObj('.searchtable center').css('display', 'none'); 
	   	}
           	$jqObj('.search__table tbody').append(data);
           	//make calculations and add to grand total 
	   	var uprice = $jqObj(data).find('td.uprice').text(); //console.log(uprice); 
	   	var taxpc = $jqObj(data).find('td.taxpc').text(); //console.log(taxpc);
	   	var quant = $jqObj(data).find('input[type="number"]').val(); //console.log(quant);
	   	var price = $jqObj(data).find('td.totalprice').text(); //console.log(price);
	   	//cals tax
		var prevtaxretail = parseFloat($jqObj('.sels__stab__comnt_right .sales-tax').text()); 
		var taxretail = (parseFloat(taxpc) * parseFloat(uprice)) / 100;
		var taxretailn = taxretail + prevtaxretail; 
              	$jqObj('.sels__stab__comnt_right .sales-tax').text(taxretailn.toFixed(2)); 
	   	//cals subtotal
		var prevsubtotalretail = parseFloat($jqObj('.sels__stab__comnt_right .sub-total').text());
		var subtotalretail = parseFloat(uprice);
	        var subtotalretailn = subtotalretail + prevsubtotalretail; 
                $jqObj('.sels__stab__comnt_right .sub-total').text(subtotalretailn.toFixed(2)); 
	   	//cals grandtotal
		var prevgrandtotalretail = parseFloat($jqObj('.sels__stab__comnt_right .grand-total').text());
		var grandtotalretail = parseFloat(price);
	      	var grandtotalretailn = grandtotalretail + prevgrandtotalretail; 
              	$jqObj('.sels__stab__comnt_right .grand-total').text(grandtotalretailn.toFixed(2)); 	
	        $jqObj('#amount-tendered').val(grandtotalretailn.toFixed(2)); 
	   	$jqObj.post("sale_ui.php", { updtses : true, grandtotal : grandtotalretailn, 
						subtotal : 0.00, taxtotal: 0.00 })
	   	      .done(function(data){ console.log(data); });
		$jqObj('#addproductforsale .modal-body input[type="text"]').each(function(){ 
           	   //if($jqObj(this).attr('name') == 'product_name'){ $jqObj(this).val(''); }
           	   if($jqObj(this).attr('name') == 'product_quantity'){ $jqObj(this).val(''); }
                   if($jqObj(this).attr('name') == 'product_gross_total'){ $jqObj(this).val(''); }
	        }); 
	        $jqObj('#addproductforsale .modal-body textarea').each(function(){ 
                   if($jqObj(this).attr('name') == 'product_comments'){ $jqObj(this).val(''); }
	        }); //console.log(pname + pquantity + pgrosstotal + pcomments);
		$jqObj('#addproductforsale').modal('hide');	 
	    });
      });
     </script>

    </div>
  </div>
</div>

<?php $dbf->closeDBlink(); ?>
