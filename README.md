# frmwk

    /**
     * @param int $start
     * @param int $limit
     * @return array
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function cbestsellers(int $categoryId = 0, int $start, int $limit): array
    {
		if($categoryId == 0){ 
	  		//array for products & categories
	  		$products = []; $categories = [];	
	  		//get date field
	  		$current_date = new \DateTime(date('Y-m-d 23:59:59')); 
	  		$prior_date = new \DateTime(date('Y-m-d 23:59:59', strtotime('-2 months')));
          	$sitecategories = $this->getConfiguration()->getCategoryFilter();
	  		//criteria
	  		$criteria = \Doctrine\Common\Collections\Criteria::create();
	  		$criteria->where(\Doctrine\Common\Collections\Criteria::expr()->lt('datePurchased', $current_date));
	  		$criteria->andWhere(\Doctrine\Common\Collections\Criteria::expr()->gt('datePurchased', $prior_date));
          	$criteria->orderBy(['ordersId' => 'DESC']); //dump($criteria); exit();

          	//find all orders matching the criteria
      	  	$orders = $this->orderRetailRepository->matching($criteria); //echo count($orders); dump($orders); exit;
          	$count=1; 
	  		foreach ($orders as $order) {
            	foreach ($order->getProducts() as $orderProduct) {
            	  try{
                  	$product = $this->productRepository->find($orderProduct->getProductsId());
                  	if($product != null){ 
		     			$pcategories = $product->getProductsCategoriesIds(); $flag=0;
		     			foreach($pcategories as $pcid){ if(in_array($pcid, $sitecategories)){ $flag=1; break; }}
                     	if($flag == 1){
							foreach($pcategories as $pcid){			 
			   					if(array_key_exists($pcid, $categories)){ 
									$val = $categories[$pcid]; $val++; 
									$categories[$pcid] = $val;  
			   					} else {  $categories[$pcid] = 1; }	
							}
		     			}
                 	}
	      		  } catch(Exception $e){ continue; }	
            	}
            	$count++;
          	}
          	arsort($categories); //dump($categories); exit;
	  		$categorylist = array_keys($categories); //dump($categorylist); exit;
          	/**
           	 * Return category list as per request
           	 */
	  		$returnCategories = '';
          	if(isset($start) && isset($limit)){
	     		$returnCategories = array_slice($categorylist, $start, $limit);
             	$count = count($returnCategories);
          	} else {
	     		$count = count($categorylist);
	     		$returnCategories = array_slice($categorylist, 0, $count);
	  		}

	  		return ['results' => $returnCategories, 'count' => $count];
        }// end if($categoryId == 0) 
    }
