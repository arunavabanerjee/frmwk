<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
*/

require('includes/application_top.php');
require(DIR_WS_INCLUDES . 'template_top.php');
?>

<table border="0" width="100%" cellspacing="0" cellpadding="2">
	<tr><td>
		<table border="0" width="100%" cellspacing="0" cellpadding="0">
    	<?php 
    	$total_quantity_in_pos = 0; $total_amount = 0.00;	 
		$products_query_raw_top = 
    		"select p.products_id, p.products_model, sum(pa.quantity) as paquantity, p.products_price, pd.products_name, count(pa.products_attributes_id) as paattributes ".
    		"from ".TABLE_PRODUCTS." p, ".TABLE_PRODUCTS_DESCRIPTION." pd, ".TABLE_PRODUCTS_ATTRIBUTES." pa ".
    		"where pd.products_id = p.products_id and pd.language_id ='".$languages_id."' and p.products_quantity > 0 ".
    						"and pa.location = \"shop\" and pa.products_id = p.products_id ".
    		"group by p.products_id ". 
    		"order by pd.products_name";
		$products_query_top = tep_db_query($products_query_raw_top);
		while ($products = tep_db_fetch_array($products_query_top)) { 
			$total_quantity_in_pos += $products['paquantity'];	
			$total_amount += $products['paquantity'] * $products['products_price'];
		}
    	?>
        <tr>
        	<td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
        	<td class="pageHeading">
  				<span style="position:relative;font-size:12px; color:#000;padding:0 5px;">
  					<?php echo HEADING_TITLE_PRODUCT_QUANTITY . $total_quantity_in_pos; ?> 
  				</span> 
  				<span style="float:right;position:absolute;font-size:12px; color:#000;padding:0 5px;">
  					<?php echo HEADING_TITLE_PRODUCT_AMOUNT . number_format(floatval($total_amount)) ; ?> 
  				</span>  
        	</td>
            <td class="pageHeading" align="right">
            	<?php echo tep_draw_separator('pixel_trans.gif', HEADING_IMAGE_WIDTH, HEADING_IMAGE_HEIGHT); ?>			
            </td>
        </tr>
    	</table>
    </td></tr>
    <tr><td>
    	<table border="0" width="100%" cellspacing="0" cellpadding="0">
         	<tr><td valign="top">
         	<table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCT_NUMBER; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCT_NAME; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCT_MODEL; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCT_ATTRIBUTES; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCT_QTY; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCT_WSPRICE; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCT_ROW_TOTAL; ?></td>
          	  </tr>
			<?php define(MAX_DISPLAY_SEARCH_RESULTS_LOCAL, 200);
  			if (isset($HTTP_GET_VARS['page']) && ($HTTP_GET_VARS['page'] > 1)) 
  				$rows = $HTTP_GET_VARS['page'] * MAX_DISPLAY_SEARCH_RESULTS_LOCAL - MAX_DISPLAY_SEARCH_RESULTS_LOCAL;

    			$products_query_raw = 
    				"select p.products_id, p.products_model, sum(pa.quantity) as paquantity, p.products_price, pd.products_name, count(pa.products_attributes_id) as paattributes ".
    				"from ".TABLE_PRODUCTS." p, ".TABLE_PRODUCTS_DESCRIPTION." pd, ".TABLE_PRODUCTS_ATTRIBUTES." pa ".
    				"where pd.products_id = p.products_id and pd.language_id ='".$languages_id."' and p.products_quantity > 0 ".
    						"and pa.location = \"shop\" and pa.products_id = p.products_id ".
    				"group by p.products_id ". 
    				"order by pd.products_name";

  				
				$products_query = tep_db_query($products_query_raw);

				$products_split = new splitPageResults($HTTP_GET_VARS['page'], MAX_DISPLAY_SEARCH_RESULTS_LOCAL, $products_query_raw, 
  														$products_query_numrows); 

  				while ($products = tep_db_fetch_array($products_query)) { ?>
          		<tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href='<?php echo tep_href_link(FILENAME_CATEGORIES, 'action=new_product_preview&read=only&pID=' . $products['products_id'] . '&origin=' . FILENAME_POS_PRODUCTS_WHOLESALE_PRICE . '?page=' . $HTTP_GET_VARS['page'], 'NONSSL'); ?>'">
                	<td class="dataTableContent" style="padding-left:8px;padding-right:8px;"><?php echo $products['products_id']; ?></td>

                	<td class="dataTableContent" style="padding-left:8px;padding-right:8px;"><?php echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=new_product_preview&read=only&pID=' . $products['products_id'] . '&origin=' . FILENAME_POS_PRODUCTS_WHOLESALE_PRICE . '?page=' . $HTTP_GET_VARS['page'], 'NONSSL') . '">' . $products['products_name'] . '</a>'; ?></td>

                	<td class="dataTableContent" style="padding-left:12px;padding-right:8px;"><?php echo $products['products_model']; ?></td>

					<td class="dataTableContent" style="padding-left:12px;padding-right:8px;"><?php echo $products['paattributes']; ?></td>

                	<td class="dataTableContent" style="padding-left:12px;padding-right:8px;"><?php echo $products['paquantity']; ?></td>

                   <td class="dataTableContent" style="padding-left:12px;padding-right:8px;"><?php echo $products['products_price']; ?></td>

                	<td class="dataTableContent" style="padding-left:12px;padding-right:8px;"><?php echo $products['paquantity'] * $products['products_price']; ?></td>
          		</tr>
			<?php } ?>
    		</table>
    		</td></tr>
          	<tr><td colspan="3">
          	<table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td class="smallText" valign="top"><?php echo $products_split->display_count($products_query_numrows, MAX_DISPLAY_SEARCH_RESULTS_LOCAL, $HTTP_GET_VARS['page'], TEXT_DISPLAY_NUMBER_OF_PRODUCTS); ?></td>
                <td class="smallText" align="right"><?php echo $products_split->display_links($products_query_numrows, MAX_DISPLAY_SEARCH_RESULTS_LOCAL, MAX_DISPLAY_PAGE_LINKS, $HTTP_GET_VARS['page']); ?>&nbsp;</td>
              </tr>
            </table>
        	</td></tr>
        	</table>
    </td></tr>
</table>

<?php
require(DIR_WS_INCLUDES . 'template_bottom.php');
require(DIR_WS_INCLUDES . 'application_bottom.php');
?>
