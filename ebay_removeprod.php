<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use DTS\eBaySDK\Constants;
use DTS\eBaySDK\Trading\Services;
use DTS\eBaySDK\Trading\Types;
use DTS\eBaySDK\Trading\Enums;

class RemoveProductsCommand extends ContainerAwareCommand {
	protected function configure()
	{
		$this
		// the name of the command (the part after "bin/console")
		->setName('remove-products')
			
		// the short description shown while running "php bin/console list"
		->setDescription('Removes products from eBay - All/Selected ')
			
		// the full command description shown when running the command with
		// the "--help" option
		->setHelp("This command allows you to remove all/selected products from ebay.");
	}
	
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$output->writeln([
			'Product Remover From BigRick Ebay Starting',
			'==============================================', '',
		]);
	
		$connection = $this->getContainer()->get('database_connection');
		$config = require __DIR__.'/../../../app/config/ebay.php';
		
		//a. returns a list of the ids of the items currently selling on ebay
		//$ebaySellingItems = $this->getMyEbaySelling($connection, $config, $output); 	
                //b. get a list of items that are in db and that are not in db
                //$itemsNotInDB = $this->listItemsForRemoval($connection, $config, $output, $ebaySellingItems);
                //c. get a list of items that are in db and that are not in db
                //$itemsNotInDB = $this->listItemsForRemoval($connection, $config, $output, $ebaySellingItems);
                //$this->doRemove($connection, $config, $output);


                //a. returns a list of the ids of the items currently selling on ebay
	        // list returned as ebayId => pmodellist
		$ebaySellingItems = $this->getMyEbaySellingByPModel($connection, $config, $output); 	
		//b. get the exclusions list as provided
		$exclusions = $this->getExclusionsListToRemove($connection, $config, $output);
		//c. list items to remove based on product model
		$itemsToRemove = $this->listItemsForRemovalByPModel($connection, $config, $output, 
									$ebaySellingItems, $exclusions);
		//d. remove the items found by product model
		$output->writeln(['Removing Items By Product Model', 
				'-------------------------------------', '']);
		//$this->doRemoveUsingList($connection, $config, $output, $itemsToRemove);
		//e. create a manufacturer list to remove
		//$customListToRemove = $this->getManufacturerListToRemove($connection, $config, $output);
                //f. remove the items found by manufacturer
                $output->writeln(['Removing Items By Manufacturer List', 
                                '---------------------------------------', '']);
                //$this->doRemoveUsingList($connection, $config, $output, $customListToRemove);
	
		$output->writeln([
			'Done.',
			'================================================','',
		]);
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
         	$request->RequesterCredentials->eBayAuthToken = 
						$config[($config['environment'] == 'sandbox') ? 'sandbox' : 'production']['authToken'];

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
				//\Symfony\Component\VarDumper\VarDumper::dump( $item ); 
				//\Symfony\Component\VarDumper\VarDumper::dump( $item->SKU ); exit;
		
				$output->writeln(['Ebay Item: '.$item->ItemID.' Item->SKU: '.$item->SKU, '' ]);
		 		array_push($fetchItems, $item->ItemID);

                 		/*printf( "(%s) %s: %s %.2f\n", $item->ItemID, $item->Title,
                  		$item->SellingStatus->CurrentPrice->currencyID, $item->SellingStatus->CurrentPrice->value );*/
               		     }
            	       }
            	      $pageNum += 1; 
        	} while (isset($response->ActiveList) && $pageNum <= $response->ActiveList->PaginationResult->TotalNumberOfPages);
	
		$output->writeln([ 'Total Number Of Products In Selling List : '.count($fetchItems) ]);
        	$output->writeln([ '========================================================', '' ]);

		return $fetchItems;
	}

        private function getMyEbaySellingByPModel(\Doctrine\DBAL\Connection $connection, array $config, OutputInterface $output){ 
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
         	$request->RequesterCredentials->eBayAuthToken = 
						$config[($config['environment'] == 'sandbox') ? 'sandbox' : 'production']['authToken'];

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
			     $counter=1;	
          		     foreach ($response->ActiveList->ItemArray->Item as $item) {
				//\Symfony\Component\VarDumper\VarDumper::dump( $item ); if($counter++ == 6){ exit; }
				if(isset($item->Variations)){ 
			           $variationSKU = ''; //$output->writeln(['Variation Items', '']);
				   foreach($item->Variations->Variation as $variation){
				     $variationSKU .= $variation->SKU . ',';
				     //$output->writeln(['Ebay Item: '.$item->ItemID.' Item->SKU: '.$variation->SKU, '' ]);
				   }
			          $fetchItems[$item->ItemID] = $variationSKU;
				} else{
				   //$output->writeln(['Ebay Item: '.$item->ItemID.' Item->SKU: '.$item->SKU, '' ]);
		 		   $fetchItems[$item->ItemID] = $item->SKU;
				}
                 		/*printf( "(%s) %s: %s %.2f\n", $item->ItemID, $item->Title,
                  		  $item->SellingStatus->CurrentPrice->currencyID, $item->SellingStatus->CurrentPrice->value );*/
               		     }
            	       }
            	      $pageNum += 1; 
        	} while (isset($response->ActiveList) && $pageNum <= $response->ActiveList->PaginationResult->TotalNumberOfPages);
	
		$output->writeln([ 'Total Number Of Products In Selling List : '.count($fetchItems) ]);
        	$output->writeln([ '========================================================', '' ]);
		//\Symfony\Component\VarDumper\VarDumper::dump($fetchItems);

		return $fetchItems;

	}
	
	private function listItemsForRemoval($connection, $config, $output, $ebayItemIds){
	  /*lists all product ids for removal*/
	  $productIdsInDB = array();
	  $productIdsNotInDB = array();

          foreach ($ebayItemIds as $ebayId){
          	$ebayItem = $connection->fetchAssoc('select * from ebay_items where ebay_item_id = '. $ebayId);
          	if(!empty($ebayItem)){
            		//$output->writeln(['EbayId => Product Id -- '.$ebayId.' => '.$ebayItem['products_id'], '']);
            		array_push($productIdsInDB, $ebayItem['products_id']);
          	}else{
            		$output->writeln(['Ebay Id Not Found in ebay_items: '.$ebayId, '']);
			array_push($productIdsNotInDB, $ebayItem['products_id']);
          	}
           }

	   $output->writeln(['Total Product Ids In Xsales DB: '.count($productIdsInDB)]);
	   $output->writeln(['Total Product Ids Not In Xsales DB: '.count($productIdsNotInDB)]);

	   return $productIdsNotInDB;
	}


        private function listItemsForRemovalByPModel($connection, $config, $output, $ebayItemIdsSKU, $exclusionList){
          /*lists all product ids for removal*/
          $productIdsToRemove = array();

          foreach ($ebayItemIdsSKU as $ebayId => $pmodels){
		/*check if pmodel is in exclusionslist*/
		if(strstr($pmodels, ',')){
		  $prmodels = explode(',', $pmodels); 
		  $pmodelsArr = array_filter($prmodels);
		  foreach($pmodelsArr as $prmodel){
			if(in_array($prmodel, $exclusionList)){ 
                        	array_push($productIdsToRemove, "$ebayId"); break;
                  	}
		  }
		} else {
		  if(in_array($pmodels, $exclusionList)){ 
			array_push($productIdsToRemove, "$ebayId"); 
		  }
		}
           }

           $output->writeln(['Total Ebay Ids To Remove: '.count($productIdsToRemove)]);
	   \Symfony\Component\VarDumper\VarDumper::dump($productIdsToRemove);

           return $productIdsToRemove;
        }

	private function doRemove($connection, $config, $output) {
		$products = $connection->fetchAll('select * from ebay_items');
		foreach ($products as $product) {
			$service = new Services\TradingService([
				'credentials' => $config[($config['environment'] == 'sandbox') ? 'sandbox' : 'production']['credentials'],
				'siteId'   => Constants\SiteIds::AU,
				'sandbox'  => ($config['environment'] == 'sandbox') ? true : false
			]);
			
			$request = new Types\EndFixedPriceItemRequestType();
			
			/**
			 * An user token is required when using the Trading service.
			 */
			$request->RequesterCredentials = new Types\CustomSecurityHeaderType();
			$request->RequesterCredentials->eBayAuthToken = $config[($config['environment'] == 'sandbox') ? 'sandbox' : 'production']['authToken'];
			
			$request->ItemID = $product['ebay_item_id'];
			$request->EndingReason = 'OtherListingError';
			//$request->SKU = $product['ebay_item_id'];

			$response = $service->endFixedPriceItem($request);
				
			/**
			 * Output the result of calling the service operation.
			 */
			if (isset($response->Errors)) {
				foreach ($response->Errors as $error) {
					$output->writeln([
						$error->SeverityCode === Enums\SeverityCodeType::C_ERROR ? 'Error' : 'Warning',
						$error->ShortMessage, $error->LongMessage,
						'Product ID: ' . $product['products_id'], ''
					]);
				}
			}
			if ($response->Ack !== 'Failure') {
				$output->writeln([
					"The item was removed from eBay with the Item number \n",
						$product['products_id'],
						''
				]);
				$connection->query('delete from ebay_items where products_id ='.$product['products_id']);
			}
		}
	}


        private function doRemoveUsingList($connection, $config, $output, $itemsToRemove) {

                foreach ($itemsToRemove as $ebayItemId) {

			$output->writeln([' Removal Started For EbayId: '.$ebayItemId, '']);

                        $service = new Services\TradingService([
                                'credentials' => $config[($config['environment'] == 'sandbox') ? 'sandbox' : 'production']['credentials'],
                                'siteId'   => Constants\SiteIds::AU,
                                'sandbox'  => ($config['environment'] == 'sandbox') ? true : false
                        ]);

                        $request = new Types\EndFixedPriceItemRequestType();

                        /**
                         * An user token is required when using the Trading service.
                         */
                        $request->RequesterCredentials = new Types\CustomSecurityHeaderType();
                        $request->RequesterCredentials->eBayAuthToken = $config[($config['environment'] == 'sandbox') ? 'sandbox' : 'production']['authToken'];

                        $request->ItemID = $ebayItemId;
                        $request->EndingReason = 'OtherListingError';
                        //$request->SKU = $product['ebay_item_id'];

                        $response = $service->endFixedPriceItem($request);

                        /**
                         * Output the result of calling the service operation.
                         */
                        if (isset($response->Errors)) {
                                foreach ($response->Errors as $error) {
                                        $output->writeln([
                                                $error->SeverityCode === Enums\SeverityCodeType::C_ERROR ? 'Error' : 'Warning',
                                                $error->ShortMessage, $error->LongMessage,
                                                'Ebay Item ID: ' . $ebayItemId, ''
                                        ]);
                                }
			}
                        if ($response->Ack !== 'Failure') {
                                $output->writeln([
                                       "The item was removed from eBay with the Item number \n",
                                       $ebayItemId, ''
                                ]);

				//if ebayId is present in XsalesDb, delete that
                                $ebay_dblisting = $connection->fetchAssoc('select * from ebay_items where ebay_item_id = '.$ebayItemId);
				if(!empty($ebay_dblisting)){
                                  $connection->query('delete from ebay_items where ebay_item_id ='.$ebayItemId );
				  $output->writeln(['Ebay Item Deleted From Xsales DB', '']);
				}
				$output->writeln(['--------------------------------------------------', '']);
                        }
			//break;
                }//end foreach
        }


	private function getManufacturerListToRemove($connection, $config, $output){

		$mproductsToRemove = array(
			'292457984754', '292457973114', '292458056892', '292458082832', '292458201258', '292457987075',
			'292457995387', '292458035708', '292458117351', '292458122771', '292458123019', '292458136784', 
			'292458136807', '292458136838', '292458166157', '292458170966', '292458172316', '292458198652', 
			'292457968220', '292457984608', '292457993887', '292458033169', '292458042163', '292458042187', 
			'292458042230', '292458084209', '292458089943', '292458111582', '292458111633', '292458119935',
			'292458126729', '292458135552', '292458135620', '292458141795', '292458160880', '292458165750', 
			'292458189396', '292458200084', '292837447655', '292837452716', '292458135806', '292458075234'
		);
		return $mproductsToRemove;
	}
	
	private function getFirstExclusionsListToRemove($connection, $config, $output){

		$pmodelExclusions = array(
			'PREM-332', 'PMP-017-B', 'BON-711', 'BON-626', 'PMP-024-B', 'BON-154-B', 'PREM-037', 'PREM-035', 
			'PREM-029', 'PREM-008', 'PREM-015', 'PREM-016', 'PREM-041', 'LUBE-022', 'LUBE-TC-011', 'PREM-027', 
			'PREM-030', 'PREM-040', 'PREM-022', 'PREM-050', 'PREM-009', 'PREM-021', 'PREM-020', 'PREM-011', 
			'PREM-002', 'PREM-028', 'PREM-001', 'PREM-012', 'PREM-017', 'PREM-010', 'PREM-032', 'DIS-266',
			'MD-031-B', 'PMP-246', 'PMP-193', 'MD-921', 'PMP-243', 'DNG-465', 'BDSL-271', 'BDSL-264', 'BDSL-261',
			'BDSL-254', 'BDSL-060', 'DIS-263', 'DIS-266', 'BON-095-B', 'BON-378', 'XR-207', 'SW-002', 'BDS-305',
			'PREM-294', 'DNG-235-B', 'BDSL-038', 'BDSL-190', 'PMP-252', 'STR-284', 'STR-283', 'BDSL-245', 'CR-251-B',
			'MD-759', 'MD-758', 'MD-757', 'STR-259', 'STR-254', 'STR-253', 'STR-252', 'STR-251', 'STR-250', 
			'STR-249', 'KT-148', 'KT-147', 'KT-146', 'KT-145', 'KT-144', 'KT-143', 'KT-142', 'HCL-265-BRACES-RED', 
			'HCL-265-BRACES-BLACK', 'HCL-024-GAG', 'XR-211', 'XR-068', 'BDSL-108-LEATHER', 'BDSL-108-FAUX',
			'BDSL-092-LEATHER', 'BDSL-092-PVC', 'BON-380', 'BDSL-161-LTHR', 'BDSL-088-LEATHER', 'BDSL-104-PVC',
			'BDSL-104-LEATHER', 'BON-109-B', 'BDSL-101', 'CR-611', 'CR-610', 'MD-299-B', 'MD-321-B', 'MD-111-B',
			'MD-106-B', 'KT-128', 'KT-129', 'MD-147-B', 'BMD-032', 'MD-687', 'MD-104-B', 'BON-058-B', 'DNG-235-B',
			'STR-127-B', 'STR-243', 'STR-061', 'STR-014-B', 'STR-085-B', 'STR-016-B', 'MD-186-B', 'MD-679', 'MD-118-B',
			'MD-120-B', 'MD-119-B', 'MD-117-B', 'MD-121-B', 'MD-659', 'BMD-026', 'BON-628', 'MD-179-B', 'BON-626', 
			'MD-138-B', 'MD-139-B', 'MD-308-B', 'MD-301-B', 'MD-642', 'MD-641', 'MD-135-B', 'MD-640', 'MD-637', 'MD-639',
			'MD-636', 'BON-214-B', 'MD-628', 'MD-627', 'MD-625', 'MD-625', 'MD-623', 'MD-123-B', 'MD-620', 'MD-618', 
			'MD-615', 'MD-613', 'MD-611', 'MD-175', 'MD-176-B', 'MD-605', 'MD-177-B', 'MD-603', 'MD-602', 'MD-596', 
			'MD-595', 'MD-594', 'MD-145-B', 'PMP-021-B', 'BMD-012', 'MD-135', 'ACC-106', 'ACC-105', 'ACC-104', 
			'ACC-100', 'ACC-099', 'ACC-097', 'ACC-096', 'MD-566', 'MD-563', 'MD-318-B', 'XR-138', 'XR-136', 'XR-113', 
			'XR-082', 'XR-078', 'XR-074', 'XR-039', 'XR-036', 'MD-324-B (2)', 'BON-558', 'STR-049-B', 'STR-227', 
			'BON-524', 'BOND-084-B', 'STR-113-B', 'STR-221', 'STR-113-B', 'STR-036-B', 'MD-291-B', 'MD-522', 
			'BON-194-B', 'BON-509', 'STR-112-B', 'STR-093-B', 'STR-090-B', 'STR-039-B', 'STR-210', 'PMP-149', 
			'BMD-031', 'NV-365', 'CR-471', 'PREM-290', 'BON-465', 'MD-241-B (3)', 'MD-272-B', 'MD-241-B (5)', 
			'MD-241-B (4)', 'MD-241-B (2)', 'MD-262-B', 'MD-268-B', 'DNG-230-B', 'BON-223-B', 'BON-441',
			'BON-438', 'BON-430', 'MD-266-B', 'MD-398', 'MD-237-B', 'MD-239-B (1)', 'MD-108-B', 'MD-391', 'BMD-011', 
			'PREM-204', 'MD-365', 'MD-364', 'BMD-043', 'MD-355', 'BMD-034', 'BMD-057', 'TAN-019', 'TAN-018', 'TAN-017', 
			'TAN-016', 'PMP-118', 'MD-331', 'BMD-024', 'PREM-134', 'BMD-010', 'BMD-008', 'MD-228', 'MD-039-B', 
			'BON-073', 'BON-084-B', 'BON-225', 'CR-084-B', 'ES-048', 'MD-154-B', 'MD-194-B', 'MD-072', 'MD-208-B', 
			'MD-359-B', 'MD-357-B', 'MD-361-B', 'MD-031-B', 'ANL-252', 'STR-084', 'STR-020-B', 'STR-079-B', 'STR-067-B', 
			'MD-035', 'MD-338', 'MD-037', 'MD-034', 'BMD-006', 'BMD-002', 'BMD-009', 'BMD-021', 'BMD-005', 'MD-249-B', 
			'PMP-225', 'MD-200-B', 'MD-215-B', 'MD-198-B', 'PMP-101', 'MD-062-B', 'MD-150-B', 'MD-162-B', 'MD-188-B', 
			'MD-143-B', 'MD-151-B', 'MD-188-B', 'MD-102-B', 'VAG-010', 'PMP-024-B', 'HCL-274-RD', 
			'HCL-274-BLK', 'HCL-265-BRACES-BLACK', 'HCL-264', 'DIS-274', 'DIS-266', 'DIS-263', 'DIS-262',
			'BON-152-B', 'BON-095-B', 'BON-832', 'BDSL-263', 'BDSL-250', 'BDSL-245', 'HCL-265-BRACES-RED', 'BDSL-156-HEAVY',
			'BDSL-156-LIGHT', 'BON-161-B-HARN', 'BDSL-044', 'BDSL-038', 'BON-091', 'HCL-198', 'BDSL-044', 'BDSL-246', 
			'HCL-273-HOOD', 'BDSL-044', 'BDSL-304', 'BDSL-125', 'BDSL-149-THICK', 'XR-090', 'PREM-547', 'PREM-546', 'PREM-545', 
			'PREM-544', 'PREM-543', 'PREM-512', 'MD-103-B', 'PREM-321', 'PREM-320', 'PREM-318', 'PREM-317', 'KT-016', 
			'DNG-245-B', 'DNG-094', 'DNG-011-B', 'MD-138', 'MD-248-B', 'ACC-110-3PK', 'ACC-110-10PK', 'MD-252-B', 'MD-222-B', 
			'DNG-096', 'MD-250-B', 'MD-366-B', 'MD-087-B', 'MD-227-B', 'MD-068-B', 'MD-367-B', 'MD-083-B', 'MD-213-B', 
			'MD-069-B', 'MD-076-B', 'DNG-088', 'md-248-b', 'PREM-611', 'PREM-612', 'PREM-610', 'PREM-104', 'PREM-103', 
			'PREM-361', 'PREM-360', 'PREM-482', 'VAG-366', 'PREM-540', 'PREM-431', 'PREM-432', 'PREM-488',
			'NV-109', 'NV-421', 'CR-411', 'NV-108', 'ACC-029', 'NV-288', 'NV-287-BLK', 'NV-287-FLESH', 'NV-287-GLWGRN', 
			'NV-287-HTPNK', 'NV-287-GLWPNK', 'NV-287-NBLUE', 'NV-291', 'PREM-205', 'KII-001', 'PREM-509', 'PREM-547', 
			'PREM-546', 'PREM-545', 'PREM-544', 'PREM-543', 'PREM-512', 'PREM-321', 'PREM-320', 'PREM-318', 'PREM-317', 
			'PREM-322', 'PREM-319', 'PREM-105', 'PREM-542', 'PREM-510', 'ACC-010', 'ACC-013', 'PHE-120', 'ACC-063-X30', 
			'ACC-063-X40', 'VB-493-PUR', 'VAG-553', 'CR-190-B-BLU', 'VB-692-PNK', 'PREM-307-FUS', 'PREM-307-LIL', 
			'PREM-307-PUR', 'CR-067-B-PUR', 'PREM-264-FUS', 'PREM-264-LIL', 'PREM-264-PUR', 'VB-498-LIL', 'VB-497-PUR', 
			'VB-290-FUS', 'VB-290-LIL', 'VB-290-PUR', 'VB-493-FUS', 'PREM-307-BLK', 'CR-190-B-AQU', 'VB-692-COR', 
			'CR-067-B-BLK', 'VB-496', 'VB-290-BLK', 'VAG-185-PUR', 'PREM-264-BLK', 'VB-498-BLK', 'VB-497-BLK', 'VB-493-BLK', 
			'VAG-197-FUS', 'VB-488', 'PREM-638', 'PREM-191-RDWHTE', 'PREM-511', 'PREM-478', 'PREM-476', 'PREM-472', 
			'PREM-164-GRN', 'PREM-159-WHI', 'PREM-312-VIO', 'PREM-188-RSE', 'PREM-187-BLU', 'PREM-182-VIT', 'PREM-180-VAN', 
			'PREM-364-VIT', 'PREM-157-BLU', 'PREM-171-RSE', 'PREM-254-GRN', 'PREM-254-RSE', 'PREM-254-YEL', 'PREM-241-BLK',
			'PREM-242-BLK', 'PREM-194-CANDY', 'PREM-178-CREAM', 'PREM-376', 'PREM-364-BLU', 'PREM-359', 'PREM-358', 
			'PREM-357', 'PREM-312-BLK', 'PREM-254-BLU', 'PREM-243', 'PREM-242-BORD', 'PREM-241-BORD', 'LUBE-TC-024', 
			'PREM-185-PUR', 'PREM-171-BLU', 'PREM-191-GRWTE', 'PREM-635', 'PREM-399', 'PREM-404', 'PREM-398', 'PREM-396',
			'PREM-394', 'PREM-393', 'PREM-392', 'PREM-391', 'PREM-390', 'PREM-385', 'NV-364', 'LUBE-TC-034', 'ACC-115', 
			'PMP-129-B', 'PMP-114-B', 'ANL-322', 'PMP-116-B', 'ACC-009'

		);
		return $pmodelExclusions;

	}

	/**
	 * Second Exclusion List on Mar02, 2019
	 */
	private function getExclusionsListToRemove($connection, $config, $output){
		$pmodelExclusions = array(
		'PREM-332', 'PMP-017-B', 'BON-711', 'BON-626', 'PMP-024-B', 'BON-154-B', 'PREM-037', 'PREM-035', 'PREM-029', 'PREM-008',
		'PREM-015', 'PREM-016', 'PREM-041', 'LUBE-022', 'LUBE-TC-011', 'PREM-027', 'PREM-030', 'PREM-040', 'PREM-022', 'PREM-050',
		'PREM-009', 'PREM-021', 'PREM-020', 'PREM-011', 'PREM-002', 'PREM-028', 'PREM-001', 'PREM-012', 'PREM-017', 'PREM-010',
		'PREM-032', 'DIS-266', 'MD-031-B', 'PMP-246', 'PMP-193', 'MD-921', 'PMP-243', 'DNG-465', 'BDSL-271', 'BDSL-264', 'BDSL-261', 
		'BDSL-254', 'BDSL-060', 'DIS-263', 'DIS-266', 'BON-095-B', 'BON-378', 'XR-207', 'SW-002', 'BDS-305', 'PREM-294', 'DNG-235-B',
		'BDSL-038', 'BDSL-190', 'PMP-252', 'STR-284', 'STR-283', 'BDSL-245', 'CR-251-B', 'MD-759', 'MD-758', 'MD-757', 'STR-259', 
		'STR-254', 'STR-253', 'STR-252', 'STR-251', 'STR-250', 'STR-249', 'KT-148', 'KT-147', 'KT-146', 'KT-145', 'KT-144', 'KT-143',
		'KT-142', 'HCL-265-BRACES-RED', 'HCL-265-BRACES-BLACK', 'HCL-024-GAG', 'XR-211', 'XR-068', 'BDSL-108-LEATHER', 'BDSL-108-FAUX',
		'BDSL-092-LEATHER', 'BDSL-092-PVC', 'BON-380', 'BDSL-161-LTHR', 'BDSL-088-LEATHER', 'BDSL-104-PVC', 'BDSL-104-LEATHER', 'BON-109-B',
		'BDSL-101', 'CR-611', 'CR-610', 'MD-299-B', 'MD-321-B', 'MD-111-B', 'MD-106-B', 'KT-128', 'KT-129', 'MD-147-B', 'BMD-032',
		'MD-687', 'MD-104-B', 'BON-058-B', 'DNG-235-B', 'STR-127-B', 'STR-243', 'STR-061', 'STR-014-B', 'STR-085-B', 'STR-016-B', 
		'MD-186-B', 'MD-679', 'MD-118-B', 'MD-120-B', 'MD-119-B', 'MD-117-B', 'MD-121-B', 'MD-659', 'BMD-026', 'BON-628', 'MD-179-B',
		'BON-626', 'MD-138-B', 'MD-139-B', 'MD-308-B', 'MD-301-B', 'MD-642', 'MD-641', 'MD-135-B', 'MD-640', 'MD-637', 'MD-639',
		'MD-636', 'BON-214-B', 'MD-628', 'MD-627', 'MD-625', 'MD-625', 'MD-623', 'MD-123-B', 'MD-620', 'MD-618', 'MD-615', 'MD-613',
		'MD-611', 'MD-175', 'MD-176-B', 'MD-605', 'MD-177-B', 'MD-603', 'MD-602', 'MD-596', 'MD-595', 'MD-594', 'MD-145-B', 'PMP-021-B',
		'BMD-012', 'MD-135', 'ACC-106', 'ACC-105', 'ACC-104', 'ACC-100', 'ACC-099', 'ACC-097', 'ACC-096', 'MD-566', 'MD-563', 'MD-318-B',
		'XR-138', 'XR-136', 'XR-113', 'XR-082', 'XR-078', 'XR-074', 'XR-039', 'XR-036', 'MD-324-B (2)', 'BON-558', 'STR-049-B', 'STR-227',
		'BON-524', 'BOND-084-B', 'STR-113-B', 'STR-221', 'STR-113-B', 'STR-036-B', 'MD-291-B', 'MD-522', 'BON-194-B', 'BON-509', 'STR-112-B',
		'STR-093-B', 'STR-090-B', 'STR-039-B', 'STR-210', 'PMP-149', 'BMD-031', 'NV-365', 'CR-471', 'PREM-290', 'BON-465', 'MD-241-B (3)',
		'MD-272-B', 'MD-241-B (5)', 'MD-241-B (4)', 'MD-241-B (2)', 'MD-262-B', 'MD-268-B', 'DNG-230-B', 'BON-223-B', 'BON-441', 'BON-438',
		'BON-430', 'MD-266-B', 'MD-398', 'MD-237-B', 'MD-239-B (1)', 'MD-108-B', 'MD-391', 'BMD-011', 'PREM-204', 'MD-365', 'MD-364', 
		'BMD-043', 'MD-355', 'BMD-034', 'BMD-057', 'TAN-019', 'TAN-018', 'TAN-017', 'TAN-016', 'PMP-118', 'MD-331', 'BMD-024', 'PREM-134',
		'BMD-010', 'BMD-008', 'MD-228', 'MD-039-B', 'BON-073', 'BON-084-B', 'BON-225', 'CR-084-B', 'ES-048', 'MD-154-B', 'MD-194-B',
		'MD-072', 'MD-208-B', 'MD-359-B', 'MD-357-B', 'MD-361-B', 'MD-031-B', 'ANL-252', 'STR-084', 'STR-020-B', 'STR-079-B', 'STR-067-B',
		'MD-035', 'MD-338', 'MD-037', 'MD-034', 'BMD-006', 'BMD-002', 'BMD-009', 'BMD-021', 'BMD-005', 'MD-249-B', 'PMP-225', 'MD-200-B',
		'MD-215-B', 'MD-198-B', 'PMP-101', 'MD-062-B', 'MD-150-B', 'MD-162-B', 'MD-188-B', 'MD-143-B', 'MD-151-B', 'MD-188-B', 'MD-102-B',
		'VAG-010', 'PMP-024-B', 'HCL-274-RD', 'HCL-274-BLK', 'HCL-265-BRACES-BLACK', 'HCL-264', 'DIS-274', 'DIS-266', 'DIS-263', 'DIS-262',
		'BON-152-B', 'BON-095-B', 'BON-832', 'BDSL-263', 'BDSL-250', 'BDSL-245', 'HCL-265-BRACES-RED', 'BDSL-156-HEAVY', 'BDSL-156-LIGHT',
		'BON-161-B-HARN', 'BDSL-044', 'BDSL-038', 'BON-091', 'HCL-198', 'BDSL-044', 'BDSL-246', 'HCL-273-HOOD', 'BDSL-044', 'BDSL-304',
		'BDSL-125', 'BDSL-149-THICK', 'XR-090', 'PREM-547', 'PREM-546', 'PREM-545', 'PREM-544', 'PREM-543', 'PREM-512', 'MD-103-B',
		'PREM-321', 'PREM-320', 'PREM-318', 'PREM-317', 'KT-016', 'DNG-245-B', 'DNG-094', 'DNG-011-B', 'MD-138', 'MD-248-B', 'ACC-110-3PK',
		'ACC-110-10PK', 'MD-252-B', 'MD-222-B', 'DNG-096', 'MD-250-B', 'MD-366-B', 'MD-087-B', 'MD-227-B', 'MD-068-B', 'MD-367-B', 
		'MD-083-B', 'MD-213-B', 'MD-069-B', 'MD-076-B', 'DNG-088', 'md-248-b', 'PREM-611', 'PREM-612', 'PREM-610', 'PREM-104', 'PREM-103',
	'PREM-361', 'PREM-360', 'PREM-482', 'VAG-366', 'PREM-540', 'PREM-431', 'PREM-432', 'PREM-488', 'NV-109', 'NV-421', 'CR-411', 'NV-108',
		'ACC-029', 'NV-288', 'NV-287-BLK', 'NV-287-FLESH', 'NV-287-GLWGRN', 'NV-287-HTPNK', 'NV-287-GLWPNK', 'NV-287-NBLUE', 'NV-291', 
		'PREM-205', 'KII-001', 'PREM-509', 'PREM-547', 'PREM-546', 'PREM-545', 'PREM-544', 'PREM-543', 'PREM-512', 'PREM-321', 'PREM-320',
		'PREM-318', 'PREM-317', 'PREM-322', 'PREM-319', 'PREM-105', 'PREM-542', 'PREM-510', 'ACC-010', 'ACC-013', 'PHE-120', 'ACC-063-X30',
		'ACC-063-X40', 'PHE-067-DELAY', 'AC-116', 'AC-053', 'AC-054', 'AC-026', 'AC-083', 'AC-082', 'AC-005-SHOW', 'AC-111', 'AC-062',
		'AC-084', 'AC-085', 'AC-106', 'AC-105', 'AC-051', 'AC-071', 'AC-115', 'AC-034', 'AC-113', 'AC-045', 'AC-031', 'AC-086', 
		'AC-095', 'AC-094', 'AC-007', 'AC-024', 'AC-055', 'AC-056', 'AC-117', 'AC-038', 'AC-098', 'AC-097', 'AC-110', 'AC-109', 
		'AC-018', 'AC-103', 'AC-010', 'AC-032-TEA', 'AC-032-TEAS', 'AC-003', 'AC-035', 'AC-036', 'AC-030', 'AC-002', 'AC-001', 
		'AC-037', 'AC-028', 'AC-029', 'AC-081', 'AC-080', 'AC-008', 'AC-078', 'AC-079', 'AC-090', 'AC-066', 'AC-013', 'AC-070', 
		'AC-087', 'AC-043', 'AC-049', 'AC-025', 'AC-039', 'AC-040', 'AC-048', 'AC-044', 'AC-114', 'AC-017', 'AC-022', 'AC-021',
		'AC-014', 'AC-091', 'AC-004-RET', 'AC-004-RETYEL', 'AC-052', 'AC-101', 'AC-016', 'AC-093', 'AC-015', 'AC-088', 'AC-058', 
		'AC-057', 'AC-089', 'AC-060', 'AC-046', 'AC-068', 'AC-069', 'AC-041', 'AC-042', 'AC-072', 'AC-108', 'AC-107', 'AC-063', 
		'AC-019', 'AC-074', 'AC-104', 'AC-075', 'AC-096', 'AC-065', 'AC-077', 'AC-076', 'AC-092', 'AC-099', 'AC-100', 'AC-067', 
		'AC-102', 'AC-009', 'AC-064', 'AC-061', 'AC-059', 'AC-050', 'AC-047'
		);
		return $pmodelExclusions;
	
	}

}
