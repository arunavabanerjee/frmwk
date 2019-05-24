<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use DTS\eBaySDK\Constants;
use DTS\eBaySDK\Trading\Services;
use DTS\eBaySDK\Trading\Types;
use DTS\eBaySDK\Trading\Enums;
use AppBundle\Utils;
use AppBundle\Utils\EbayMariaItem;

class ExportToEbayMariaCommand extends ExportToEbayCommand
{
    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('export-to-ebay-maria')

            // the short description shown while running "php bin/console list"
            ->setDescription('Exports all products to eBay Maria account.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp("This command allows you to export all products.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln([
            'Product exporter starting ... ', 
	    '======================================', '',
        ]);
        $connection = $this->getContainer()->get('database_connection');
        $config = require __DIR__.'/../../../app/config/ebay_maria.php';

        /**
	 * update existing ebay products
	 */
        $ebay_products = $this->getMyEbaySelling($connection, $config, $output);
        //\Symfony\Component\VarDumper\VarDumper::dump($ebay_products); exit;
	//$ebayProductIds = $this->updateActiveEbayProducts($connection, $config, $output, $ebay_products);

	/**
	 * export products not in listing
	 */
        $this->doExport($connection, $config, $output, $ebay_products);

        $output->writeln([
            'Done ... ', 
	    '==================================', '', ]);
    }

    private function doExport(\Doctrine\DBAL\Connection $connection, array $config, OutputInterface $output, array $ebayProductIds)
    {
	//get a list of products from ebay_items_maria
	$output->writeln(['Export Of Products Started ',
			'======================================================', '']);
	$output->writeln(['Getting a list of all Products On Ebay', '' ]);

	$productIdsEbay = array();
        foreach ($ebayProductIds as $ebayId){
          $ebayItem = $connection->fetchAssoc('select * from ebay_items_maria where ebay_item_id = '. $ebayId);
          if(!empty($ebayItem)){  
	    //$output->writeln(['EbayId => Product Id -- '.$ebayId.' => '.$ebayItem['products_id'], '']);
	    array_push($productIdsEbay, $ebayItem['products_id']); 
	  }else{
            $output->writeln(['Ebay Id Not Found in ebay_items_maria: '.$ebayId, '']); 
	  }
        }
	
	//$pids = implode(',',$productIdsEbay);
	//$output->writeln(['List Of All Product Ids To be removed from Listing -- Total: '.count($productIdsEbay), $pids, '']);
        $output->writeln(['List Of All Product Ids To be removed from Listing -- Total: '.count($productIdsEbay), '']); //exit;

	$output->writeln(['List Of Products..... Done.', 
			  '---------------------------------------------', '']); 

        $removeMoreProducts = array('45830', '28431', '34257', '26958', '51713'); 

	$listingErrorPids = array('53086', '51506', '50176', '48424', '49354', '47798', '47702', '47662', '46895', '46892', '46890', 
				'46887', '46886', '46855', '45521', '44960', '44031', '44030', '42666', '38141', '37513', '35997',
				'30984', '30422', '28336', '27264', '27262', '27109', '27104', '28336', '25488', '25487', '24667',
				'28366', '21714', '13935', '10433', '10429', '9034', '6971', '6968', '6964', '6962', '6954', '6951', 
				'6950', '6949', '6946', '6927', '5766', '45861', '45864' );

	$veriFailedProducts = array('52984', '52983', '52941', '52929', '51580', '51576', '51547', '51379', '50374', '50350', '50348', 
				'50347', '50345', '50344', '50342', '50341', '50340', '50338', '50337', '50329', '50320', '50319', 
				'50318', '50317', '50316', '50315', '50313', '50311', '50297', '50295', '50294', '50293', '50292', 
				'50291', '50290', '50289', '50287', '50285', '50284', '50283', '50281', '50280', '50278', '50276',
				'50275', '50274', '50273', '50272', '50271', '50270', '50263', '50237', '50236', '50083', '49993', 
				'49956', '49924', '49880', '49980', '49877', '49764', '49763', '49345', '49259', '49035', '48903', '48890',
				'48819', '48816', '48814', '48812', '48810', '48803', '48801', '48750', '48144', '48102', '48101',
				'47917', '47909', '47823', '47822', '47816', '47729', '47050', '47040', '46789', '46659', '46614', 
				'46530', '45543', '45443', '45308', '44832', '44831', '44823', '44626', '44451', '44209', '44010', 
				'43937', '43935', '43931', '43773', '42731', '42727', '40362', '40006', '36081', '35888', '35887', 
				'35798', '35794', '35237', '35236', '35208', '35207', '35091', '34955', '34333', '34197', '34195', 
				'34182', '33684', '33681', '32768', '32686', '32304', '31733', '31130', '31064', '30351', '29978',
				'29526', '29241', '29123', '28332', '27828', '27291', '27249', '27248', '27232', '27114', '26963', 
				'26835', '26781', '26774', '26773', '25890', '25880', '23929', '22467', '22416', '22378', '21704', 
				'21333', '21309', '21104', '20365', '18615', '18611', '16032', '15967', '15961', '14223', '13954', 
				'13952', '10727', '10724', '9723', '5514', '5511', '5298', '4620', '88', '86', '83', '81'  );
	
	$output->writeln(['Exporting New Products....................... ', '']);

        $products = $connection->fetchAll('select * from products where products_retail_status = 1 order by products_id desc');
        //$products = $connection->fetchAll('select * from products where products_id in 
	//   (select products_id from ebay_items where status = "ERROR: The item can\'t be listed or modified.") 
	//   order by products_id desc');
        //$products = $connection->fetchAll('select * from products where products_id in (53197)');
        //$products = $connection->fetchAll('select * from products where products_id in ("26958")');

        $count = 1;
	foreach ($products as $product) {  
	    $output->writeln([ 'Reading Record No: '.$count, '' ]);
            try {
		if(in_array($product['products_id'], $productIdsEbay)){
		  $output->writeln(['Product Id Already Updated: '.$product['products_id'].'.. Skipping.', 
				    '---------------------------------------------------------------', '']);
		} elseif (in_array($product['products_id'], $removeMoreProducts)){
                  $output->writeln(['Product Id Already Updated - In More Products: '.$product['products_id'].'.. Skipping.',
                                    '---------------------------------------------------------------', '']);
                } elseif (in_array($product['products_id'], $listingErrorPids)){
                  $output->writeln(['Product Id Already Updated - In Listing Error: '.$product['products_id'].'.. Skipping.',
                                    '---------------------------------------------------------------', '']);
                } elseif (in_array($product['products_id'], $veriFailedProducts)){
                  $output->writeln(['Product Id Already Updated - In Not Verified Error: '.$product['products_id'].'.. Skipping.',
                                    '---------------------------------------------------------------', '']);
		} else{
                  $output->writeln(['Product Id Not Updated: '.$product['products_id'].'..........', '']);
               	  $isError = $this->doAddOrUpdate($connection, $config, $output, $product);
		}
                /*if ($isError) {
                   $isErrorAgain = $this->doRemove($connection, $config, $output, $product);
                   if ($isErrorAgain) {
                      $connection->query('delete from ebay_items_maria where products_id ='.$product['products_id']);
                   }
                   $this->doAddOrUpdate($connection, $config, $output, $product);
                }*/
            }
            catch (\Exception $e) {
                $output->writeln([
                    'Error '. $e->getMessage(), 'At line ' . $e->getLine() . ' in file ' . $e->getFile(),
                    'Product ID: ' . $product['products_id'], ''  ]);
            }
	    if($count++ > 18000){ break; }
        }
    }

    private function getMyEbaySelling(\Doctrine\DBAL\Connection $connection, array $config, OutputInterface $output){ 
	/*fetch a list of current ebay Items by page */ 
	 $fetchItems = array();
         $service = new Services\TradingService([
            'credentials' => $config[($config['environment'] == 'sandbox') ? 'sandbox' : 'production']['credentials'],
            'siteId'   => Constants\SiteIds::AU,
            'sandbox'  => ($config['environment'] == 'sandbox') ? true : false
         ]);

         $request = new Types\GetMyeBaySellingRequestType();

         /**   
          * An user token is required when using the Trading service.
          */
         $request->RequesterCredentials = new Types\CustomSecurityHeaderType();
         $request->RequesterCredentials->eBayAuthToken = $config[($config['environment'] == 'sandbox') ? 'sandbox' : 'production']['authToken'];

         /**
          * Request that eBay returns the list of actively selling items.
          * We want 10 items per page and they should be sorted in descending order by the current price.
          */
         $request->ActiveList = new Types\ItemListCustomizationType();
         $request->ActiveList->Include = true;
         $request->ActiveList->Pagination = new Types\PaginationType();
         $request->ActiveList->Pagination->EntriesPerPage = 200;
         $request->ActiveList->Sort = Enums\ItemSortTypeCodeType::C_CURRENT_PRICE_DESCENDING;

	 $pageNum = 1;
         do {
             $request->ActiveList->Pagination->PageNumber = $pageNum;
             /*** Send the request. */
             $response = $service->getMyeBaySelling($request);
             /*** Output the result of calling the service operation. */
             $output->writeln([ "=== Ebay Items List - Page : $pageNum ===" ]);
             if (isset($response->Errors)) {
               foreach ($response->Errors as $error) {
                 $output->writeln([
                   $error->SeverityCode === Enums\SeverityCodeType::C_ERROR ? 'Error' : 'Warning',
                   $error->ShortMessage, $error->LongMessage
                 ]);
               }
             }
             if ($response->Ack !== 'Failure' && isset($response->ActiveList)) {
               foreach ($response->ActiveList->ItemArray->Item as $item) {
		 array_push($fetchItems, $item->ItemID);
                 /*printf( "(%s) %s: %s %.2f\n", $item->ItemID, $item->Title,
                  $item->SellingStatus->CurrentPrice->currencyID, $item->SellingStatus->CurrentPrice->value );*/
               }
            }
            $pageNum += 1; 
        } while (isset($response->ActiveList) && $pageNum <= $response->ActiveList->PaginationResult->TotalNumberOfPages);
	
	$output->writeln([ 'Total Number Of Products In List : '.count($fetchItems) ]);
        $output->writeln([ '=======================================', '' ]);

	return $fetchItems;
    }

    /**
     * Displays List of All Active Items Updated
     * Displays List of All Items Not Updated
     * Displays List of All Product Ids On Ebay
     *
     * Returns List of All Product Ids on Ebay
     */
    private function updateActiveEbayProducts(\Doctrine\DBAL\Connection $connection, array $config, OutputInterface $output, array $ebayItems){

	$ebayProductIdsUpdated = array();
	$ebayProductIdsNotUpdated = array();
	$productsList = array();

	$output->writeln([ 'Running An Update For All Existing Ebay Items', 
			   '===========================================', '' ]);
	$count = 1;
        foreach($ebayItems as $activeItemID){
	    $output->writeln(['Reading Ebay Item: '.$activeItemID, '']);
	    $ebay_dblisting = $connection->fetchAssoc('select * from ebay_items_maria where ebay_item_id = ' . $activeItemID); 
	    if(!empty($ebay_dblisting)){
	       $output->writeln([ 'Active Ebay Item ID Found In DB', 
				  'Updating ... ProductID: '.$ebay_dblisting['products_id'], '']);
               $products = $connection->fetchAll('select * from products where products_id in ('.$ebay_dblisting['products_id'].')');
	       $product = $products[0]; array_push($productsList, $product['products_id']);

	       $service = new Services\TradingService([
        	    'credentials' => $config[($config['environment'] == 'sandbox') ? 'sandbox' : 'production']['credentials'],
	            'siteId'      => Constants\SiteIds::AU,
        	    'sandbox'     => ($config['environment'] == 'sandbox') ? true : false
               ]);
	
		/**
		* Revise Item 
		*/              
              $request = new Types\ReviseFixedPriceItemRequestType();

	       /**
                * An user token is required when using the Trading service.
         	*/
              $request->RequesterCredentials = new Types\CustomSecurityHeaderType();
              $request->RequesterCredentials->eBayAuthToken = $config[($config['environment'] == 'sandbox') ? 'sandbox' : 'production']['authToken'];

        	/**
         	 * Finish the request object.
         	 */
              $item = Utils\EbayMariaItem::create($product, $connection, $config, $output);
              if ($item === false){ 
		  $output->writeln(['Error In Item Generation ..... Quitting..', 
			  	    '---------------------------------------' ]); 
                  array_push($ebayProductIdsNotUpdated, $activeItemID); continue; 
	      }
              $request->Item = $item; //\Symfony\Component\VarDumper\VarDumper::dump($request); exit;
	      $response = $service->reviseFixedPriceItem($request);

	      /**
               * Output the result of calling the service operation.
               */
              //$isError = false; $errorLongText = '';
              if (isset($response->Errors)) {
               foreach ($response->Errors as $error) {
                 if ($error->SeverityCode === Enums\SeverityCodeType::C_ERROR) { $isError = true; }
                 $errorLongText = $error->LongMessage;
                 $output->writeln([
                     $error->SeverityCode === Enums\SeverityCodeType::C_ERROR ? 'Error' : 'Warning',
                     $error->ShortMessage, $error->LongMessage, 'Product ID: ' . $product['products_id'], ''
                 ]);
               }
               //$connection->query('REPLAce into ebay_items values ('.$product['products_id'].', "", "ERROR: '.$error->ShortMessage.'")');
              }

              if ($response->Ack !== 'Failure') {
                $output->writeln([ "The item was updated on eBay with the Item number \n", $response->ItemID,
                                   '--------------------------------------------------', '' ]);
                if (isset($ebay_dblisting['ebay_item_id']) && !empty($ebay_dblisting['ebay_item_id'])) {
                  $connection->query('Replace into ebay_items_maria values ('.$product['products_id'].',"'.$response->ItemID.'","OK",'.time().')');
                } else {
                  $connection->query('Insert into ebay_items_maria values ('.$product['products_id'].',"'.$response->ItemID.'","OK",'.time().')');
                }
		array_push($ebayProductIdsUpdated, $activeItemID);

	      }else{
                $output->writeln([ 'Ebay Item ID: '.$activeItemID. ' Response Incurred A Failure',
                                   '-------------------------------------------------------', '' ]);
                array_push($ebayProductIdsNotUpdated, $activeItemID);
	      }	
	    } else {
		$output->writeln([ 'Ebay Item ID '.$activeItemID. ' Not Found in Xsales DB', 
				  '-------------------------------------------------------', '' ]);
                array_push($ebayProductIdsNotUpdated, $activeItemID);
	    } //end if-else
	  //if($count++ > 10){ break; }
	} // end foreach
        
	$output->writeln([ 'Ebay Active Items Updated', 
			   '========================================================','']);
	$ebayIdsUpdated = implode(',',$ebayProductIdsUpdated);
	$ebayIdsNotUpdated = implode(',',$ebayProductIdsNotUpdated);
	$pids = implode(',',$productsList);

	$output->writeln([ 'Ebay Ids Updated -- Total: '.count($ebayProductIdsUpdated), $ebayIdsUpdated, '']);
	$output->writeln([ 'Ebay Ids Not Updated -- Total: '.count($ebayProductIdsNotUpdated), $ebayIdsNotUpdated, '']);
	$output->writeln([ 'Product Ids On Ebay -- Total: '.count($productsList), $pids, '']);        

	return $productsList;
    }


    private function doAddOrUpdate($connection, $config, $output, $product)
    {
        $isError = false; $errorLongText = '';
        $ebay_listing = $connection->fetchAssoc('select * from ebay_items_maria where products_id = ' . $product['products_id']);
        //if (time() - $ebay_listing['last_export'] < 24*3600) return false;

        $service = new Services\TradingService([
            'credentials' => $config[($config['environment'] == 'sandbox') ? 'sandbox' : 'production']['credentials'],
            'siteId'      => Constants\SiteIds::AU,
            'sandbox'	  => ($config['environment'] == 'sandbox') ? true : false
        ]); 

        $output->writeln([ 'Ebay ID Not Present For Product ID: '.$product['products_id'],
		  	   'Adding Product...', '' ]);
        $request = new Types\AddFixedPriceItemRequestType(); 

        /**
         * An user token is required when using the Trading service.
         */
        $request->RequesterCredentials = new Types\CustomSecurityHeaderType();
        $request->RequesterCredentials->eBayAuthToken = $config[($config['environment'] == 'sandbox') ? 'sandbox' : 'production']['authToken'];

        /**
         * Finish the request object.
         */
        $item = Utils\EbayMariaItem::create($product, $connection, $config, $output);
        if ($item === false){
	   $output->writeln([ 'Error in Item - Product Will Not Be Added: '.$product['products_id'], 
			 '------------------------------------------------------------', '' ]); 
           $isError = true; return $isError; 
        }

        $request->Item = $item;
        /*if (isset($ebay_listing['ebay_item_id']) && !empty($ebay_listing['ebay_item_id'])) {
            $request->DeletedField = ['Item.SubTitle'];
        }*/
	//\Symfony\Component\VarDumper\VarDumper::dump($request); //exit;

        /**
         * Send the request.
         */
	 //verify the item before updation
         $verified = $this->verifyFixedPriceItem($connection, $config, $output, $item, $product);
         //\Symfony\Component\VarDumper\VarDumper::dump($request); exit;

         if( $verified ){
	    //$response = $service->reviseFixedPriceItem($request);
	    $response = $service->addFixedPriceItem($request);
	    //$isError = false; return $isError;	
         } else {
            $output->writeln([ 'Verification Failed - Product Will Not Be Added: '.$product['products_id'],
               		'-----------------------------------------------------', '' ]);
            $isError = true; return $isError;
         }
	 //\Symfony\Component\VarDumper\VarDumper::dump($response); exit;

        /**
         * Output the result of calling the service operation.
         */
        //$isError = false; $errorLongText = '';
        if (isset($response->Errors)) {
           foreach ($response->Errors as $error) {
             if ($error->SeverityCode === Enums\SeverityCodeType::C_ERROR) { $isError = true; }
	     $errorLongText = $error->LongMessage;
             $output->writeln([
                $error->SeverityCode === Enums\SeverityCodeType::C_ERROR ? 'Error' : 'Warning',
                $error->ShortMessage, $error->LongMessage, 'Product ID: ' . $product['products_id'], ''  
             ]);
           }
           //$connection->query('REPLAce into ebay_items values ('.$product['products_id'].', "", "ERROR: '.$error->ShortMessage.'")');
        }

        if ($response->Ack !== 'Failure') {
           $output->writeln([ "The item was listed to the eBay with the Item number \n", $response->ItemID, 
			      '--------------------------------------------------', '' ]);
	   if (isset($ebay_listing['ebay_item_id']) && !empty($ebay_listing['ebay_item_id'])) {
              $connection->query('Replace into ebay_items_maria values ('.$product['products_id'].',"'.$response->ItemID.'","OK",'.time().')');
	   } else {
	      $connection->query('Insert into ebay_items_maria values ('.$product['products_id'].',"'.$response->ItemID.'","OK",'.time().')');	   
	   }
           //$connection->query('REPLAce into ebay_items_maria values ('.$product['products_id'].', "'.$response->ItemID.'", "OK", '.time().')');
        } 
        return $isError;
    }

    private function verifyFixedPriceItem($connection, $config, $output, $item, $product){
       /*create a request to verify the item*/
       $service = new Services\TradingService([
            'credentials' => $config[($config['environment'] == 'sandbox') ? 'sandbox' : 'production']['credentials'],
            'siteId'   => Constants\SiteIds::AU,
            'sandbox'  => ($config['environment'] == 'sandbox') ? true : false
       ]);
       $request = new Types\VerifyAddFixedPriceItemRequestType();
       
       /**
        * An user token is required when using the Trading service.
        */
       $request->RequesterCredentials = new Types\CustomSecurityHeaderType();
       $request->RequesterCredentials->eBayAuthToken = $config[($config['environment'] == 'sandbox') ? 'sandbox' : 'production']['authToken'];
       $request->Item = $item;

       $response = $service->verifyAddFixedPriceItem($request);
       if (isset($response->Errors)) {
          foreach ($response->Errors as $error) {
              $output->writeln([ 
                $error->SeverityCode === Enums\SeverityCodeType::C_ERROR ? 'Error' : 'Warning',
                $error->ShortMessage, $error->LongMessage ]);
		  
	      //if listing violates duplicate listing policy, then item not added
              if(strstr($error->ShortMessage, 'Listing may violate the Duplicate listing policy') || 
                  strstr($error->ShortMessage, 'Listing violates the Duplicate listing policy') ){
	         $output->writeln([ "==================================================", 
		       	            " ITEM CHECKED AS DUPLICATE LISTING - ". $product['products_id'],
				    "==================================================", ''  ]);
		 //delete the previous item
		 preg_match('#\((.*?)\)#', $error->LongMessage, $dupEbayItemId); 
		 $output->writeln([ "Ebay Item To Be Removed: ", $dupEbayItemId[1], '' ]);

                 /*if($ebay_listing['ebay_item_id'] != $dupEbayItemId[1]){ 
		     $result = $this->doRemove($connection, $config, $output, $product, $dupEbayItemId[1]);
		     if($result){ return false; } else{ return false; }
                  } else {
                     return true;
                  }*/

		  return false;
 	       }	
           }
       }

       if( $response->Ack !== 'Failure' ){
           $output->writeln([ "ITEM VERIFIED - WILL BE ADDED/UPDATED", '' ]);
	   return true;
       } else {
           $output->writeln([ "ITEM NOT VERIFIED - WILL NOT BE ADDED/UPDATED", '' ]);
           return false;
       }

    }

    private function doRemove($connection, $config, $output, $product, $ebayId='')
    {
        if($ebayId == ''){
          $products = $connection->fetchAll('select * from ebay_items_maria where products_id = ' . $product['products_id']);
        }else{  $products = array(); array_push($products, $ebayId);  }
        $isError = false;
        foreach ($products as $product) {
            $service = new Services\TradingService([
                'credentials' => $config[($config['environment'] == 'sandbox') ? 'sandbox' : 'production']['credentials'],
                'siteId'      => Constants\SiteIds::AU,
                'sandbox'	  => ($config['environment'] == 'sandbox') ? true : false
            ]);
            $request = new Types\EndFixedPriceItemRequestType();

            /**
             * An user token is required when using the Trading service.
             */
            $request->RequesterCredentials = new Types\CustomSecurityHeaderType();
            $request->RequesterCredentials->eBayAuthToken = $config[($config['environment'] == 'sandbox') ? 'sandbox' : 'production']['authToken'];

	    if($ebayId == ''){ 
		 $request->ItemID = $product['ebay_item_id'];
	    }else{ 
		 $request->ItemID = $product;
	    }	
            $request->EndingReason = 'OtherListingError';
            //$request->SKU = $product['ebay_item_id'];
            $response = $service->endFixedPriceItem($request);

            /**
             * Output the result of calling the service operation.
             */
            if (isset($response->Errors)) {
                foreach ($response->Errors as $error) {
                    if ($error->SeverityCode === Enums\SeverityCodeType::C_ERROR) {  $isError = true; }
		    if($ebayId == ''){
                      $output->writeln([
                        $error->SeverityCode === Enums\SeverityCodeType::C_ERROR ? 'Error' : 'Warning',
                        $error->ShortMessage, $error->LongMessage, 'Product ID: ' . $product['products_id'], '' ]); 
		    } else {
                      $output->writeln([
                        $error->SeverityCode === Enums\SeverityCodeType::C_ERROR ? 'Error' : 'Warning',
                        $error->ShortMessage, $error->LongMessage, 'Ebay Id: ' . $ebayId, '' ]); 
		    }
                }
            }
            if ($response->Ack !== 'Failure') {
                if($ebayId == ''){
                  $output->writeln([ "The item was removed from eBay with the Item number \n", $product['products_id'], '' ]);
                  $connection->query('delete from ebay_items_maria where products_id ='.$product['products_id']); 
                  $isError = false;
                } else {
                  $output->writeln([ "The item was removed from eBay with the Item number \n", $ebayId, '' ]);
                  $isError = false;
		}  
            }
        }
        return $isError;
    }
}
