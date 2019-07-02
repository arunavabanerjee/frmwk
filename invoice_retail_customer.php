<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');

  require_once('ext/dompdf/autoload.inc.php');
  use Dompdf\Dompdf;

  require(DIR_WS_CLASSES . 'currencies.php');
  $currencies = new currencies();

  $oID = tep_db_prepare_input($HTTP_GET_VARS['oID']);
  $orders_query = tep_db_query("select orders_id from " . TABLE_ORDERS_RETAIL . " where orders_id = '" . (int)$oID . "'");

  include(DIR_WS_CLASSES . 'order_retail.php');
  $order = new order_retail($oID);
?>
<?php
  //var_dump($HTTP_GET_VARS); var_dump($HTTP_SERVER_VARS);
  $action = (isset($HTTP_GET_VARS['submitBtn']) ? $HTTP_GET_VARS['submitBtn'] : '');
  if($action == 'Generate PDF'){ 
     $cookie = $HTTP_SERVER_VARS['HTTP_COOKIE']; $cookieArray = explode(';',$cookie);

     $url = $HTTP_SERVER_VARS["HTTP_REFERER"].'&'.$cookieArray[0]; 
     $html = file_get_contents($url); //var_dump($html);
     //$html = mb_convert_encoding($html,'HTML-ENTITIES','UTF-8');
     $replHtml = preg_replace('/<div class="nodisplay">.*?<\/div>/s', '', $html);

     //$doc = new DOMDocument(); $doc->loadHTML($html);
     //$tables = $doc->getElementsByTagName('table');
     //foreach($tables as $table) { $content = $doc->saveHTML($table); }
     //var_dump($content);

     //create the pdf from the dom
     //$dompdf = new Dompdf(); $dompdf->loadHtml($html);
     $dompdf = new Dompdf(); $dompdf->loadHtml($replHtml);
     $dompdf->setPaper('A4','portrait'); 
     $dompdf->render();
     //$dompdf->stream("invoice.pdf", array("Attachment"=>0)); exit();
     $output = $dompdf->output(); 
     file_put_contents('./images/Invoice.pdf', $output);

     $filepath = './images/Invoice.pdf';
     // Process download
     if(file_exists($filepath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($filepath).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        flush(); // Flush system output buffer
        readfile($filepath);
        exit;
     }

  }
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html <?php echo HTML_PARAMS; ?>>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
<title><?php echo TITLE; ?></title>
<link rel="stylesheet" type="text/css" href="includes/old_stylesheet_new_in_mindsparx_admin_folder.css">
</head>
<body style="color: #000000;">
<div class="nodisplay">  
<form id="generate-pdf" method="get" action="<?php echo $PHP_SELF; ?>">
   <input type="hidden" name="oID" value="<?php echo $HTTP_GET_VARS['oID']?>" />
   <!--<input type="hidden" name="osCAdminID" value="<?php echo $HTTP_GET_VARS['osCAdminID']?>" />-->
   <input type="submit" name="submitBtn" id="submitBtn" value="Generate PDF" />
</form>
</div>

<!-- body_text //-->
<div style="max-width: 1170px;width: 100%; margin: 0 auto;">
<table border="0" width="100%" cellspacing="0" cellpadding="2">
  <tr>
    <td><table border="0" width="100%" cellspacing="0" cellpadding="0"> 
      <tr>
        <td class="pageHeading"><?php echo nl2br('RMV Management Pty Ltd'); ?></td>
      </tr>     
      <tr>
        <td class="pageHeading"><?php echo nl2br(STORE_NAME_ADDRESS); ?></td>
        <td class="pageHeading" align="right"><?php //echo tep_image(DIR_WS_HTTP_RETAIL_CATALOG . 'images/' . 'store_logo.png', STORE_NAME); ?></td>
        <!--<a href="http://pdf-ace.com/pdfme?cache=1&cache_for=86400" target= "_blank">Save as PDF</a>-->
      </tr>
      <tr>
      	<td colspan="2" class="pageHeading">
      		Invoice: # R<?php echo $oID; ?><br /><br />
      	</td>
      </tr>
    </table></td>
  </tr>
  <tr>
    <td><table width="100%" border="0" cellspacing="0" cellpadding="2">
      <tr>
        <td colspan="2"><?php echo tep_draw_separator(); ?></td>
      </tr>
      <tr>
        <td valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main"><strong><?php echo ENTRY_SOLD_TO; ?></strong></td>
          </tr>
          <tr>
            <td class="main">
            	<?php
            		if ($order->customer['customers_verified'] != '0000-00-00 00:00:00') {
            			echo 'VERIFIED RETAIL<br />';
            		} 
            		echo tep_address_format($order->customer['format_id'], $order->billing, 1, '', '<br />'); 
            	?>
            </td>
          </tr>
          <tr>
            <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo $order->customer['telephone']; ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo '<a href="mailto:' . $order->customer['email_address'] . '"><u>' . $order->customer['email_address'] . '</u></a>'; ?></td>
          </tr>
        </table></td>
        <td valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main"><strong><?php echo ENTRY_SHIP_TO; ?></strong></td>
          </tr>
          <tr>
            <td class="main"><?php echo tep_address_format($order->delivery['format_id'], $order->delivery, 1, '', '<br />'); ?></td>
          </tr>
        </table></td>
      </tr>
    </table></td>
  </tr>
  <tr>
    <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
  </tr>
  <tr>
    <td><table border="0" cellspacing="0" cellpadding="2">
      <tr>
        <td class="main"><strong><?php echo ENTRY_PAYMENT_METHOD; ?></strong></td>
        <td class="main"><?php echo $order->info['payment_method']; ?></td>
      </tr>
    </table></td>
  </tr>
  <tr>
    <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
  </tr>
  <tr>
    <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr class="dataTableHeadingRow">
      	<td class="dataTableHeadingContent">&nbsp;</td>
        <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCTS; ?></td>
        <td class="dataTableHeadingContent" align="center"><?php echo TABLE_HEADING_PRODUCTS_MODEL; ?></td>
        <!--<td class="dataTableHeadingContent" align="center"><?php //echo 'Warehouse Code'; ?></td>
        <td class="dataTableHeadingContent">Barcode</td>
        <td class="dataTableHeadingContent" align="center"><?php //echo TABLE_HEADING_MANUFACTURER; ?></td>
        <td class="dataTableHeadingContent" align="center">MPN</td>-->
        <td class="dataTableHeadingContent" align="center">Weight</td>
        <td class="dataTableHeadingContent" align="center"><?php echo TABLE_HEADING_TAX; ?></td>
        <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_PRICE_EXCLUDING_TAX; ?></td>
        <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_PRICE_INCLUDING_TAX; ?></td>
        <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_TOTAL_EXCLUDING_TAX; ?></td>
        <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_TOTAL_INCLUDING_TAX; ?></td>
      </tr>
<?php

	$epf_query = tep_db_query("select * from " . TABLE_EPF . " e join " . TABLE_EPF_LABELS . " l where (e.epf_id = l.epf_id) and (l.languages_id = " . (int)$languages_id . ") and l.epf_active_for_language order by epf_order");
	$sBarcodeField = '';
    while ($e = tep_db_fetch_array($epf_query)) {  // retrieve all active extra fields
      $field = 'extra_value';
      if ($e['epf_uses_value_list']) {
        if ($e['epf_multi_select']) {
          $field .= '_ms';
        } else {
          $field .= '_id';
        }
      }
      $field .= $e['epf_id'];
      $epf[] = array('id' => $e['epf_id'],
                     'label' => $e['epf_label'],
                     'uses_list' => $e['epf_uses_value_list'],
                     'multi_select' => $e['epf_multi_select'],
                     'columns' => $e['epf_num_columns'],
                     'display_type' => $e['epf_value_display_type'],
                     'show_chain' => $e['epf_show_parent_chain'],
                     'search' => $e['epf_advanced_search'],
                     'keyword' => $e['epf_use_as_meta_keyword'],
                     'field' => $field);
      if ( $e['epf_label'] == 'Barcode' ) {
      	$sBarcodeField = $field;
      }
    }

    $iTotalItems = 0;
    $totalShipping = 0;
    for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
      // begin Product Extra Fields
	    $query = "select p.products_id, pd.products_name, pd.products_description, p.products_model, p.products_quantity, p.products_image, pd.products_url, p.products_price, p.products_tax_class_id, p.products_date_added, p.products_date_available, p.manufacturers_id, p.products_weight";
	    foreach ($epf as $e) {
	      $query .= ", pd." . $e['field'];
	    }
	    $query .= " from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where p.products_id = '" . (int)$order->products[$i]['products_id'] . "' and pd.products_id = p.products_id and pd.language_id = '" . (int)$languages_id . "'";
	    $product_info_query = tep_db_query($query);
	    $e = tep_db_fetch_array($product_info_query); $mpnValue='';
	  // end Product Extra Fields	    	
      echo '      <tr class="dataTableRow" style="background-color: #FFF;">' . "\n" .
           '        <td class="dataTableContent" valign="middle" align="center">'. '<img src="http://xsales.com.au/images/'.$e['products_image'] .'" alt="'.$e['products_image'].'" title="'.$e['products_image'].'" width="60" height="60" />' . '</td>' . "\n" .
           '        <td class="dataTableContent" valign="middle">'. $order->products[$i]['qty'] . '&nbsp;x ' . stripslashes($order->products[$i]['name']);
		
      $iTotalItems += $order->products[$i]['qty'];
      if (isset($order->products[$i]['attributes']) && (($k = sizeof($order->products[$i]['attributes'])) > 0)) { 
         for ($j = 0; $j < $k; $j++) { echo $order->products[$i]['attributes'][$j]["options_sku"];
       $opt_query = "select mpn from ". TABLE_PRODUCTS_ATTRIBUTES . " where options_sku = '". $order->products[$i]['attributes'][$j]["options_sku"] ."'"; $opt_info_query = tep_db_query($opt_query); $mpnValue = tep_db_fetch_array($opt_info_query);
          echo '<br /><nobr><small>&nbsp;<i> - ' . $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'];
          if ($order->products[$i]['attributes'][$j]['price'] != '0') echo ' (' . $order->products[$i]['attributes'][$j]['prefix'] . $currencies->format($order->products[$i]['attributes'][$j]['price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . ')';
          echo '</i></small></nobr>';
        }
      }

      echo '        </td>' . "\n" .
            '        <td class="dataTableContent" valign="middle" align="center">' . $order->products[$i]['model'] . '</td>' . "\n";

      //    '        <td class="dataTableContent" valign="middle" align="center">' . $e['extra_value32'] . '</td>' . "\n";
      
      /*if ( $e[ $sBarcodeField ] ) {
      	echo '		<td><img src="barcodegen.php?barcode=' . $e[ $sBarcodeField ] . '&width=300&height=50"></td>';
      }
      else {
      	echo '		<td></td>';
      }*/

      //$man_name = tep_db_query('select manufacturers_name from manufacturers where manufacturers_id = ' . $e['manufacturers_id']);
      //$man_name = tep_db_fetch_array($man_name);
      //$totalShipping += $e['products_weight'];
      //echo '		<td class="dataTableContent" valign="middle" align="center">' . $man_name['manufacturers_name'] . '</td>';
      //if($e['extra_value3'] != ''){ 
      //  echo '    <td class="dataTableContent" valign="middle" align="center">' . $e['extra_value3'] . '</td>';
      //} else {
      //  echo '   <td class="dataTableContent" valign="middle" align="center">' . $mpnValue['mpn'] . '</td>';
      //}

      echo '		<td class="dataTableContent" valign="middle" align="center">' . $e['products_weight'] . '</td>';
      echo '        <td class="dataTableContent" valign="middle" align="center">' . tep_display_tax_value($order->products[$i]['tax']) . '%</td>' . "\n" .
           '        <td class="dataTableContent" align="right" valign="middle"><strong>' . $currencies->format($order->products[$i]['final_price'], true, $order->info['currency'], $order->info['currency_value']) . '</strong>' . (($order->products[$i]['special']) ? '(special)' : '') . '</td>' . "\n" .
           '        <td class="dataTableContent" align="right" valign="middle"><strong>' . $currencies->format(tep_add_tax($order->products[$i]['final_price'], $order->products[$i]['tax'], true), true, $order->info['currency'], $order->info['currency_value']) . '</strong>' . (($order->products[$i]['special']) ? '(special)' : '') . '</td>' . "\n" .
           '        <td class="dataTableContent" align="right" valign="middle"><strong>' . $currencies->format($order->products[$i]['final_price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . '</strong>' . (($order->products[$i]['special']) ? '(special)' : '') . '</td>' . "\n" .
           '        <td class="dataTableContent" align="right" valign="middle"><strong>' . $currencies->format(tep_add_tax($order->products[$i]['final_price'], $order->products[$i]['tax'], true) * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . '</strong>' . (($order->products[$i]['special']) ? '(special)' : '') . '</td>' . "\n";
      echo '      </tr>' . "\n";
    }
?>
      <tr>
        <td align="right" colspan="12"><table border="0" cellspacing="0" cellpadding="2" width="100%">
<?php
  for ($i = 0, $n = sizeof($order->totals); $i < $n; $i++) {
    echo '          <tr>' . "\n" .
    	 '			<td colspan="7" width="70%">&nbsp;</td>'.
         '            <td align="right" class="main bbottom" width="20%">' . $order->totals[$i]['title'] . '</td>' . "\n" .
         '            <td align="right" class="main bbottom" width="10%">' . $order->totals[$i]['text'] . '</td>' . "\n" .
         '          </tr>' . "\n";
  }
?>

<tr>
	<td colspan="7" width="70%">&nbsp;</td>
	<td align="right" class="main bbottom" width="20%">Total Items</td>
    <td align="right" class="main bbottom" width="10%"><?php echo $iTotalItems; ?></td>
    
</tr>
<tr>
	<td colspan="7" width="70%">&nbsp;</td>
	<td align="right" class="main bbottom" width="20%">Total Weight</td>
    <td align="right" class="main bbottom" width="10%"><?php echo number_format($totalShipping, 2); ?>kg</td>
    
</tr>

        </table></td>
      </tr>
    </table></td>
  </tr>

</table>
<!-- body_text_eof //-->

<br />
</div>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
