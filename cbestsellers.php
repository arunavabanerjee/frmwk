

//pageservice
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
	  //get date field
	  $current_date = date('Y-m-d 23:59:59'); 
	  $prior_date = date('Y-m-d 23:59:59', strtotime('-6 months')); 
      $sitecategories = $this->getConfiguration()->getCategoryFilter();
	  
	  $criteria = new \Doctrine\Common\Collections\Criteria();
	  $criteria->where($criteria->expr()->lt('date_purchased', $current_date));
	  $criteria->andWhere($criteria->expr()->gt('date_purchased', $prior_date));

      $orders = $this->orderRetailRepository->matching($criteria);
	  $products = []; $categories = [];
      foreach ($orders as $order) {
         foreach ($order->getProducts() as $orderProduct) {
           $product = $this->productRepository->find($orderProduct->getProductsId()); 
		   $pcategories = $product->getProductsCategoriesIds(); $flag=0;
		   foreach($pcategories as $pcid){ if(in_array($pcid, $sitecategories)){ $flag=1; break; }}
           if($flag == 1){
			foreach($pcategories as $pcid){			 
			  if(array_key_exists($pcid, $categories){ 
				$val = $categories[$pcid]; $categories[$pcid] = ++$val;  
			  } else {
				$categories[$pcid] = 1; 
			  }	
			}
		   }	
         }
      }
	  $count = count($categories);
      return ['results' => $categories, 'count' => $count];
    }
