<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');

  require(DIR_WS_CLASSES . 'currencies.php');
  $currencies = new currencies();

   //if generate coupon is set, generate coupon 
  if (!empty($HTTP_POST_VARS["generate_coupon"])) { 
	$checkCouponQuery = "SELECT d.discount_codes_id, d.products_id, d.customers_id, d.discount_values FROM ". TABLE_DISCOUNT_CODES . " d " .
						"WHERE d.products_id LIKE '%" . $HTTP_POST_VARS["product_id"] . "%' AND d.customers_id =".$HTTP_POST_VARS["customer_id"];
	//echo $checkCouponQuery;
	$result = tep_db_num_rows(tep_db_query($checkCouponQuery)); 
    if($result == 0){ 
		if($HTTP_POST_VARS['discount_pc_amt'] != ""){
		   $sql_data_array = array('products_id' => tep_db_prepare_input($HTTP_POST_VARS["product_id"]), 'categories_id' => '',
                                   'manufacturers_id' => '', 'excluded_products_id' => '',
                                   'customers_id' => tep_db_prepare_input($HTTP_POST_VARS["customer_id"]),
                                   'orders_total' => '0', 'order_info' => '', 'exclude_specials' => '',
                                   'discount_codes' => substr(md5(uniqid(rand(), true)), 0, 8),
                                   'discount_values' => tep_db_prepare_input($HTTP_POST_VARS['discount_pc_amt']),
                                   'minimum_order_amount' => 0, 'expires_date' => date('Y-m-d', strtotime("+6 months")),
                                   'number_of_use' => 1, 'number_of_products' => 1 ); 
    		tep_db_perform(TABLE_DISCOUNT_CODES, $sql_data_array);
    		$messageStack->add_session(SUCCESS_DISCOUNT_CODE_INSERTED, 'success'); 
         } else {
			$messageStack->add_session(ERROR_DISCOUNT_CODE_INSERTED, 'error');
		 }
    } else {
		if( ($HTTP_POST_VARS['discount_pc_amt'] != "") && ($HTTP_POST_VARS['discount_codes'] != "") ){
		   $discount_data = tep_db_fetch_array(tep_db_query($checkCouponQuery));
		   $sql_data_array = array('products_id' => $HTTP_POST_VARS["product_id"], 'categories_id' => '',
                                   'manufacturers_id' => '', 'excluded_products_id' => '',
                                   'customers_id' => $HTTP_POST_VARS["customer_id"],
                                   'orders_total' => '0', 'order_info' => '', 'exclude_specials' => '',
                                   'discount_codes' => $HTTP_POST_VARS['discount_codes'],
                                   'discount_values' => tep_db_prepare_input($HTTP_POST_VARS['discount_pc_amt']),
                                   'minimum_order_amount' => 0, 'expires_date' => date('Y-m-d', strtotime("+6 months")),
                                   'number_of_use' => 1, 'number_of_products' => 1 ); 

           tep_db_perform(TABLE_DISCOUNT_CODES, $sql_data_array, 'update', "discount_codes_id = '" . (int)$discount_data['discount_codes_id'] . "'");
           $messageStack->add_session(SUCCESS_DISCOUNT_CODE_UPDATED, 'success');
        } else {
			$messageStack->add_session(ERROR_DISCOUNT_CODE_INSERTED, 'error');
		}
    }
  } // endif (!empty($HTTP_POST_VARS["generate_coupon"])

   //if send email is set, create email and send 
  if (!empty($HTTP_POST_VARS["send_email"])) { 
	//var_dump($HTTP_POST_VARS); exit;
	$customerIDs = explode(',',$HTTP_POST_VARS["customers"]);
	foreach($customerIDs as $cID){
		$customers_query = tep_db_query("SELECT customers_id, customers_firstname, customers_lastname, customers_email_address FROM " . TABLE_CUSTOMERS_RETAIL . 
										" WHERE customers_id =".$cID);
		$message = '<table>'; $cemail_address =''; $cfname =''; $clname ='';
		while ( $custrow = tep_db_fetch_array($customers_query) ) { //var_dump($custrow); 
			$cemail_address = $custrow['customers_email_address']; 
			$cfname = $custrow['customers_firstname'];	$clname = $custrow['customers_lastname'];	
			$discountcodes_query = tep_db_query("SELECT d.discount_codes_id, d.products_id, d.customers_id, d.discount_codes, d.discount_values FROM ". 
												TABLE_DISCOUNT_CODES ." d WHERE d.customers_id =".$custrow['customers_id']); 
			$message .= '<tr><td> Product Name </td><td> Discount Code </td><td> Discount Value </td></tr>';
			while ( $discrow = tep_db_fetch_array($discountcodes_query) ) { 
			   $productdesc_query = tep_db_query("SELECT pd.products_id, pd.products_name FROM ".TABLE_PRODUCTS_DESCRIPTION ." pd WHERE pd.products_id=".$discrow['products_id']);
			   $prow = tep_db_fetch_array($productdesc_query);
			   $discount_value = strstr($discrow['discount_values'], '%') ? $discrow['discount_values'] : '$'.$discrow['discount_values'];	
			   $message .= '<tr><td>'.$prow['products_name'].'</td><td>'.$discrow['discount_codes'].'</td><td>'.$discount_value.'</td></tr>';
			}
		}
		$message .= '</table>';
		$from = "info@xsales.com.au";
    	$subject = "Discount Codes for Your Wishlist";
		 
		//Let's build a message object using the email class
    	$mimemessage = new email(array('X-Mailer: osCommerce'));
    
    	// Add footer notes
    	$message .= 
      		'<br /><br /><br /><br /><br /><br /><center><small>' . 
    		'To stop receiving this email click <a href="http://www.xsales.com.au/account_newsletters.php">here</a>.<br />XSales, PO Box 474 Riverwood, 2210, NSW, Australia.' . 
			'</small></center>';

    	// Build the text version
    	$text = strip_tags($message);
    	if (EMAIL_USE_HTML == 'true') {
      		$mimemessage->add_html($message, $text);
    	} else {
      		$mimemessage->add_text($message);
    	}
		$mimemessage->build_message(); 

		if (!empty($cemail_address)) { //var_dump($cemail_address); exit;
      		$mimemessage->send($cfname . ' ' . $clname, $cemail_address, '', $from, $subject, '', true);
			$messageStack->add(sprintf(NOTICE_EMAIL_SENT_TO, $cemail_address), 'success');
      	}      
	}
  } //end if (!empty($HTTP_POST_VARS["send_email"]))

    /** get list of customers */
  function tep_get_customers($customers_array = '') {
    if (!is_array($customers_array)) $customers_array = array();
    $customers_query = tep_db_query("SELECT customers_id, customers_firstname, customers_lastname, customers_notes FROM " . TABLE_CUSTOMERS_RETAIL ." WHERE customers_notes != '' ORDER BY customers_id DESC ");
    while ($customers = tep_db_fetch_array($customers_query)) {
      $customers_note = explode(',',$customers['customers_notes']); $cnotes = array_filter($customers_note);
	  $flag = 0; foreach($cnotes as $wpId){ if(is_numeric($wpId)){ $flag=1; } }
	  if($flag==1){
		$customers_array[] = array('id' => $customers['customers_id'], 'text' => $customers['customers_firstname'].' '.$customers['customers_lastname']);
	  }
    }
    return $customers_array;
  }

  require(DIR_WS_INCLUDES . 'template_top.php');
?>

<table width="100%">
<tr class="dataTableHeadingRow"><td class="dataTableHeadingContent" colspan="9">
<form action="<?php echo $current_page; ?>" method="get">
<?php echo TEXT_SELECT_CUST . tep_draw_pull_down_menu('customer', tep_get_customers(), $customer); ?>
<input type="submit" value="<?php echo TEXT_BUTTON_SUBMIT; ?>">
</form>
</td></tr>
<tr class="dataTableContent"><td class="dataTableContent" colspan="9"><h3 style="margin:0;">
<?php echo HEADING_TITLE; ?></h3> 
</td></tr>
<tr class=\"dataTableHeadingRow\"><td class=\"dataTableHeadingContent\" colspan="9">
<h6 style="margin-top:6px;margin-bottom:6px;"><?php echo TEXT_INSTRUCT_1; ?></h6>
</td></tr>

<?php   
  //print("<form action=\"$current_page\" method=\"post\">");
  print ("<tr class=\"dataTableHeadingRow\">
		  <td class=\"dataTableHeadingContent\" style=\"padding-left:2px\">".' '."</td>
		  <td class=\"dataTableHeadingContent\">". TABLE_HEADING_CUSTOMER_ID ."</td>
          <td class=\"dataTableHeadingContent\">". TABLE_HEADING_CUSTOMERS ."</td>
          <td class=\"dataTableHeadingContent\">". TABLE_HEADING_WISHLIST_PRODUCT_IMG ."</td>
          <td class=\"dataTableHeadingContent\">". TABLE_HEADING_WISHLIST_PRODUCT_NAME ."</td>
          <td class=\"dataTableHeadingContent\">". TABLE_HEADING_PRODUCTS_PRICE ."</td>
          <td class=\"dataTableHeadingContent\">". TABLE_HEADING_DISCOUNT_IN_PC_AMT ."</td>
          <td class=\"dataTableHeadingContent\">". TABLE_HEADING_DISCOUNT_COUPON ."</td>
		  <td class=\"dataTableHeadingContent\">".' '."</td></tr>");

	$customers_wishlist = tep_db_query("SELECT c_retail.customers_id, c_retail.customers_firstname, c_retail.customers_lastname, c_retail.customers_email_address, ". 
										"c_retail.customers_notes FROM " . TABLE_CUSTOMERS_RETAIL . " c_retail WHERE c_retail.customers_notes != '' " . 
										"ORDER BY c_retail.customers_id DESC LIMIT 200");

	while ( $row = tep_db_fetch_array($customers_wishlist) ) { //get wishlist product ids
		$wproductids = $row['customers_notes']; $wishlist_pids = explode(',' , $wproductids);
		$wishlists = array_filter($wishlist_pids); $custRecCnt = 0; //var_dump($wishlists);
		foreach($wishlists as $wpId){ 
			if(! is_numeric($wpId)){ break; } 
			elseif($custRecCnt == 0){ 
				print("<tr class=\"dataTableRow clickable\" onmouseover=\"rowOverEffect(this)\" onmouseout=\"rowOutEffect(this)\" 
							data-target=\"accordian_".$row['customers_id']."\">"); 
				print("<td class=\"dataTableContent\"><input type=\"checkbox\" name=\"customer_id\" value=\"". $row['customers_id']."\"></td>");
				print("<td class=\"dataTableContent\"><input type=\"hidden\" name=\"customer_id\" value=\"". $row['customers_id'] ."\">" . $row["customers_id"] . "</td>");
				print("<td class=\"dataTableContent\">" . $row["customers_firstname"] . ' ' . $row["customers_lastname"] . "</td>");
				print("<td class=\"dataTableContent\">" . ' ' . "</td>"); print("<td class=\"dataTableContent\">" . ' ' . "</td>");
				print("<td class=\"dataTableContent\">" . ' ' . "</td>"); print("<td class=\"dataTableContent\">" . ' ' . "</td>");
				print("<td class=\"dataTableContent\">" . ' ' . "</td>"); print("<td class=\"dataTableContent\">" . ' + ' . "</td></tr>"); 
				print("<tr><td colspan=\"9\"><div id=\"accordian_".$row['customers_id']."\" style=\"display:none;\"><table>"); 
			}

			$query = "SELECT * FROM " . TABLE_PRODUCTS_DESCRIPTION . " pd, " . TABLE_PRODUCTS . " p ". 
						"WHERE pd.products_id=p.products_id and p.products_id = $wpId and pd.language_id = " .(int)$languages_id . 
						" ORDER BY p.products_id DESC "; //echo $query; 
			$dquery = "SELECT disc.products_id, disc.customers_id, disc.discount_codes, disc.discount_values FROM ". TABLE_DISCOUNT_CODES . " disc " .
						"WHERE disc.products_id =". $wpId ." AND disc.customers_id=". $row['customers_id'] ;
			$wpproduct = tep_db_query($query);  $wpdiscount = tep_db_query($dquery); 
  			while ( $prow = tep_db_fetch_array($wpproduct) ) {  $drow = tep_db_fetch_array($wpdiscount);
		    	print("<tr class=\"dataTableRow\" onmouseover=\"rowOverEffect(this)\" onmouseout=\"rowOutEffect(this)\">"); 
				print("<form action=\"$current_page\" method=\"post\">");
				print("<td class=\"dataTableContent\"><input type=\"checkbox\" name=\"customer_id\" value=\"". $row['customers_id']."\"></td>
					<td class=\"dataTableContent\"><input type=\"hidden\" name=\"customer_id\" value=\"". $row['customers_id'] ."\">" . $row["customers_id"] . "</td>
					<td class=\"dataTableContent\">" . $row["customers_firstname"] . ' ' . $row["customers_lastname"] . "</td>
					<td class=\"dataTableContent\"><img src='". 'https://xsales.com.au/images/'. $prow["products_image"]. "' width=20 height=20 /></td>
    				<td class=\"dataTableContent\"><input type=\"hidden\" name=\"product_id\" value=\"". $prow['products_id'] ."\">" . $prow["products_name"] . "</td>
    				<td class=\"dataTableContent\">" . $prow["products_price"] . "</td> 
					<td class=\"dataTableContent\"><input name=\"discount_pc_amt\" type=\"text\" value=\"". $drow['discount_values'] . "\"></td>
					<td class=\"dataTableContent\"><input name=\"discount_codes\" type=\"text\" value=\"". $drow['discount_codes'] . "\" readonly=\"true\"></td>");
				if($drow['discount_codes'] != ""){
				 print("<td class=\"dataTableContent\"><input type=\"submit\" name=\"generate_coupon\" value=\"" . TABLE_HEADING_REGENERATE_DISCOUNT . "\" style=\"height:23px;\"></td>");
				}else{
				 print("<td class=\"dataTableContent\"><input type=\"submit\" name=\"generate_coupon\" value=\"" . TABLE_HEADING_GENERATE_DISCOUNT . "\" style=\"height:23px;\"></td>");
				}
				print("</form>");
				print("</tr>");
			}
			$custRecCnt++; 	
		} // end foreach 
		if($custRecCnt > 0){  print("</table></div></td></tr>"); }
	} //end while 

    print ("<form action=\"$current_page\" method=\"post\">
			<tr><td colspan=\"1\" align=\"right\" style=\"padding-top:10px;\">
			<input type=\"submit\" id=\"send_email\" name=\"send_email\" value=\"". TEXT_BUTTON_EMAIL ."\" style=\"width:100%;float:left;\">
			<input type=\"hidden\" id=\"customers\" name=\"customers\" value=\"\">
			<input type=\"hidden\" id=\"products\" name=\"products\" value=\"\">
			<input type=\"hidden\" name=\"inputupdate\" value=\"yes\"></td></tr>
			</form>"); 
	//print ("</form>");
?>
</td>
</tr>
</table>

<script>
var $jqry = jQuery.noConflict(); 
$jqry('#send_email').click(function(){ //alert('this');
 var records = []; 
 $jqry('tr.dataTableRow').each(function(){ 
   $jqry(this).find('input[type=checkbox]').each(function(){ 
     if($jqry(this).is(':checked')){ 
	   var cname = $jqry(this).attr('value'); records.push(cname);
	 }
   });
 });
 //alert(records); 
 $jqry('#customers').val(records); 
 if(records.length > 0){
   $jqry(this).parent().parent().parent().submit();
 }else{ return false; }
});

$jqry('tr.clickable').click(function(){
  var accordianid = $jqry(this).attr('data-target'); 
  if($jqry('#'+accordianid).css('display').toLowerCase() == "block"){
  	$jqry('#'+accordianid).css("display","none");
  } else {
  	$jqry('#'+accordianid).css("display","block");
  }
});

</script>

<?php
  require(DIR_WS_INCLUDES . 'template_bottom.php');
  require(DIR_WS_INCLUDES . 'application_bottom.php');
?>
