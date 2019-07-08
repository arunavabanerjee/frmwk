<?php

namespace App\Controller;

use App\Entity\Manufacturer;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PageService;
use App\Services\ReviewService;
use App\Services\UserService;
use Doctrine\ORM\NoResultException;
use PayPal\CoreComponentTypes\BasicAmountType;
use PayPal\EBLBaseComponents\DoExpressCheckoutPaymentRequestDetailsType;
use PayPal\EBLBaseComponents\PaymentDetailsType;
use PayPal\IPN\PPIPNMessage;
use PayPal\PayPalAPI\DoExpressCheckoutPaymentReq;
use PayPal\PayPalAPI\DoExpressCheckoutPaymentRequestType;
use PayPal\PayPalAPI\GetExpressCheckoutDetailsReq;
use PayPal\PayPalAPI\GetExpressCheckoutDetailsRequestType;
use PayPal\Service\PayPalAPIInterfaceServiceService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RadioType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Validator\Constraints\CardScheme;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Validation;

class ShopController extends Controller
{
    /**
     * @Route("/", name="home_page")
     * @param PageService $pageService
     * @param CartService $cartService
     * @param Request $request
     * @param UserService $userService
     * @return Response
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function home(PageService $pageService, CartService $cartService, Request $request, UserService $userService): Response
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));

        $cartInfo = $cartService->getCart();

        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($cookieCountry);

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        $products = $pageService->home();
        
        $blogPosts = $pageService->blog();


        $response = $this->render($pageService->getConfiguration()->getName().'/home.html.twig', [
            'products' => $products,
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'page_name' => 'home_page',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'blog_posts' => $blogPosts,
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }

    /**
     * @Route("/latest", name="latest_page")
     * @param PageService $pageService
     * @param CartService $cartService
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function latest(PageService $pageService, CartService $cartService, Request $request, UserService $userService): Response
    {
        $category = $request->get('category');
        //var_dump($category);
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));
        $page = $request->get('page', 1) - 1;
        $start = $page * $pageService->getConfiguration()->getNumProductsPerPage();

        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($cookieCountry);

        $cartInfo = $cartService->getCart();

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        //$latest = $pageService->latest($start, $pageService->getConfiguration()->getNumProductsPerPage());
        if($category !== NULL){
             $latest = $pageService->latestnew($category, $start, $pageService->getConfiguration()->getNumProductsPerPage());   
       } else {
            $latest = $pageService->latest($start, $pageService->getConfiguration()->getNumProductsPerPage());
       }

        $response = $this->render($pageService->getConfiguration()->getName().'/latest.html.twig', [
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'products' => $latest['results'],
            'count' => $latest['count'],
            'pages' => ceil($latest['count'] / $pageService->getConfiguration()->getNumProductsPerPage()),
            'page' => $page + 1,
            'visible_pages' => $pageService->getVisiblePages($page + 1, ceil($latest['count'] / $pageService->getConfiguration()->getNumProductsPerPage())),
            'page_name' => 'latest_page',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }

    /**
     * @Route("/buy/{category}/{productId}.html", name="product_page")
     * @param int $productId
     * @param string $category
     * @param PageService $pageService
     * @param CartService $cartService
     * @param Request $request
     * @return Response
     * @throws \InvalidArgumentException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function product(
        int $productId,
        string $category,
        PageService $pageService,
        CartService $cartService,
        Request $request,
        UserService $userService
    ): Response
    {
        $productInfo = $pageService->product($productId, $category);
        $addToCartForm = $this->createFormBuilder(null, ['allow_extra_fields' => true])
            ->setAction($this->generateUrl('product_page', ['category' => $category, 'productId' => $productId]))
            ->setMethod('POST')
            ->getForm();

        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));
        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($cookieCountry);

        $addToCartForm->handleRequest($request);

        if ($addToCartForm->isSubmitted() && $addToCartForm->isValid()) {
            $data = $addToCartForm->getExtraData();
            $cartService->add($productId, $data['Quantity'], $data['ProductOption'] ?? []);

            $redirect = $this->redirectToRoute('cart_page');
            $redirect->headers->setCookie($cartService->getCartCookie());
            $redirect->headers->setCookie($cartService->getCountryCookie());
            $redirect->headers->setCookie($userService->getUserCookie());

            return $redirect;
        }

        $cartInfo = $cartService->getCart();

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        $response = $this->render($pageService->getConfiguration()->getName().'/product.html.twig', [
            'product' => $productInfo['product'],
            'form' => $addToCartForm->createView(),
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'page_name' => 'product_page',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'page_service' => $pageService,
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }

    /**
     * @Route("/buy/{category}", name="category_page")
     * @param string $category
     * @param PageService $pageService
     * @param Request $request
     * @param CartService $cartService
     * @return Response
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function category(string $category, PageService $pageService, Request $request, CartService $cartService,UserService $userService): Response
    {
        $categoryTemplate = $pageService->getCategoryTemplate($category);
        $page = $request->get('page', 1) - 1;
        $start = $page * $pageService->getConfiguration()->getNumProductsPerPage();

        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($cookieCountry);

        $categoryInfo = $pageService->category($category, $start, $pageService->getConfiguration()->getNumProductsPerPage());

        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));

        $cartInfo = $cartService->getCart();

        if ($category == '') {
            $metaTitle = '';
            $metaDescription = '';
            $metaKeywords = '';
            $header = '';
        } elseif ($category == '') {
            $metaTitle = '';
            $metaDescription = '';
            $metaKeywords = '';
            $header = '';
        } else {
            $metaTitle = '';
            $metaDescription = '';
            $metaKeywords = '';
            $header = '';
        }

        $response = $this->render($pageService->getConfiguration()->getName().'/'.$categoryTemplate.'.html.twig', [
            'products' => $categoryInfo['results'],
            'count' => $categoryInfo['count'],
            'category_info' => $categoryInfo['category'],
            'pages' => ceil($categoryInfo['count'] / $pageService->getConfiguration()->getNumProductsPerPage()),
            'page' => $page + 1,
            'category' => $category,
            'visible_pages' => $pageService->getVisiblePages($page + 1, ceil($categoryInfo['count'] / $pageService->getConfiguration()->getNumProductsPerPage())),
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'page_name' => $category,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }

    /**
     * @Route("/specials", name="specials_page")
     * @param PageService $pageService
     * @param Request $request
     * @param CartService $cartService
     * @return Response
     * @throws \Exception
     */
    public function specials(PageService $pageService, Request $request, CartService $cartService, UserService $userService): Response
    {
        $page = $request->get('page', 1) - 1;
        $type = $request->get('type', 'any');
        $start = $page * $pageService->getConfiguration()->getNumProductsPerPage();

	if($type === 'valentines'){
	   $specials = $pageService->specials(0, $type, $start, $pageService->getConfiguration()->getNumProductsPerPage());
	} elseif($type === 'halloween'){
           $specials = $pageService->specials(0, $type, $start, $pageService->getConfiguration()->getNumProductsPerPage());
	} elseif($type === 'christmas'){
           $specials = $pageService->specials(0, $type, $start, $pageService->getConfiguration()->getNumProductsPerPage());
	} elseif($type === 'fathers-day'){
           $specials = $pageService->specials(0, $type, $start, $pageService->getConfiguration()->getNumProductsPerPage());
	} elseif($type === 'mothers-day'){
           $specials = $pageService->specials(0, $type, $start, $pageService->getConfiguration()->getNumProductsPerPage());
	} elseif($type === 'thanksgiving'){
           $specials = $pageService->specials(0, $type, $start, $pageService->getConfiguration()->getNumProductsPerPage());
	} else {
	   $specials = $pageService->specials(0, $type, $start, $pageService->getConfiguration()->getNumProductsPerPage());
	}

        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));
        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($cookieCountry);

        $cartInfo = $cartService->getCart();

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        if ('any' === $type) {
            $template = 'specials';
        } else {
            $template = 'specials_sub';
        }

        $response = $this->render($pageService->getConfiguration()->getName().'/'.$template.'.html.twig', [
            'products' => $specials['results'],
            'count' => $specials['count'],
            'pages' => ceil($specials['count'] / $pageService->getConfiguration()->getNumProductsPerPage()),
            'page' => $page + 1,
            'visible_pages' => $pageService->getVisiblePages($page + 1, ceil($specials['count'] / $pageService->getConfiguration()->getNumProductsPerPage())),
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'page_name' => 'specials_page',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'page_service' => $pageService,
            'type' => $type,
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }


    /**
     * @Route("/my-account", name="my_account_page")
     * @param PageService $pageService
     * @param Request $request
     * @param CartService $cartService
     * @return Response
     * @throws \Exception
     */
    public function myAccount(PageService $pageService, Request $request, CartService $cartService, UserService $userService): Response
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($cookieCountry);
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));

		$user = $userService->getUser(); 
		$formdata = $request->request->get('form'); 

		/** variables for wishlist */
		$allow_addToCart = $request->request->get('addtocart');
		$allow_removeFromWishlist = $request->request->get('removefromlist');
		$product_id = $request->request->get('productId');

        if (null === $userService->getUser()) {
            $redirect = $this->redirectToRoute('login_page');
            $redirect->headers->setCookie($cartService->getCartCookie());
            $redirect->headers->setCookie($cartService->getCountryCookie());
            $redirect->headers->setCookie($userService->getUserCookie());

            return $redirect;

        } else {

       		$customerForm = $this->createFormBuilder(null, ['allow_extra_fields' => true])
            		->setAction($this->generateUrl('my_account_page'))
					->add('customersId', TextType::class, array( 'data' => $user->getCustomersId(), 'attr' => array( 'readonly' => true ))) 
           			->add('email', EmailType::class, array('data' => $user->getCustomersEmailAddress(), 'attr' => array( 'readonly' => true )))
            		->add('first_name', TextType::class, array('data' => $user->getCustomersFirstname(), 'required' => true ))
            		->add('last_name', TextType::class, array('data' => $user->getCustomersLastname(), 'required' => true )) 
					->add('save_customer', SubmitType::class, ['label' => 'Submit Changes'])
            		->setMethod('POST')->getForm();

       		$changePasswordForm = $this->createFormBuilder(null, ['allow_extra_fields' => true, 'attr' => ['id' => 'ch-pass-form']])
            		->setAction($this->generateUrl('my_account_page'))
            		->add('password', PasswordType::class ) 
            		->add('confirm_password', PasswordType::class ) 
					->add('customersId', HiddenType::class, array( 'data' => $user->getCustomersId(), 'attr' => array( 'readonly' => true ))) 
					->add('save_password', SubmitType::class, ['label' => 'Change Password'])
            		->setMethod('POST')->getForm();
		}

		$customerForm->handleRequest($request);
		$changePasswordForm->handleRequest($request); 

		if(isset($formdata['save_customer'])){
		if ($customerForm->isSubmitted() && $customerForm->isValid()) { 
            $data = $customerForm->getData(); 
			$result = $userService->updateCustomer($data['customersId'], $data['email'], $data['first_name'], $data['last_name']);

			if($result){ $this->addFlash('success','Account Details Updated'); } else { $this->addFlash('error','Account Details Could Not Be Updated'); }

            $redirect = $this->redirectToRoute('my_account_page');
            $redirect->headers->setCookie($cartService->getCartCookie());
            $redirect->headers->setCookie($cartService->getCountryCookie());
            $redirect->headers->setCookie($userService->getUserCookie());

            return $redirect;
        }}

		if(isset($formdata['save_password'])){
		if ($changePasswordForm->isSubmitted() && $changePasswordForm->isValid()) {
            $data = $changePasswordForm->getData(); 
			$result = $userService->updatePassword($data['customersId'], $data['password']);

			if($result){ $this->addFlash('success','Password Has Been Updated'); } else { $this->addFlash('error','Password Could Not Be Updated'); }

            $redirect = $this->redirectToRoute('my_account_page');
            $redirect->headers->setCookie($cartService->getCartCookie());
            $redirect->headers->setCookie($cartService->getCountryCookie());
            $redirect->headers->setCookie($userService->getUserCookie());

            return $redirect;
        }}

		/** methods for the wishlists */
		if( isset($allow_removeFromWishlist) && isset($product_id) ){  
			//dump($request); exit;
			$curr_Wishlist = $userService->getUser()->getCustomersNotes();
			$mod_WishList = array_diff($curr_Wishlist, array($product_id)); 
			if( $userService->setCustomerWishlist($mod_WishList) ){
				 $this->addFlash('success','Product Has Been Removed From Your Wishlist');
			} else {			
				$this->addFlash('error','Product Could Not Be Removed From Wishlist');
			}
            $redirect = $this->redirectToRoute('my_account_page');
            $redirect->headers->setCookie($cartService->getCartCookie());
            $redirect->headers->setCookie($cartService->getCountryCookie());
            $redirect->headers->setCookie($userService->getUserCookie());

			return $redirect;
		}		
		if( isset($allow_addToCart) && isset($product_id) ){
			//dump($request); exit; 
			$wishlistProduct = $pageService->getProduct($product_id);
			$wpattributes = $wishlistProduct->getProductsAttributes();  
			$flag = 0; $productOption = array(); //dump($wpattributes); exit;
			foreach( $wpattributes as $wpkey => $wpdata ){
				if($wpkey == 'NoAttributes'){ $flag = 1;
					foreach( $wpdata['values'] as $key => $value ){ //dump($value); exit; 
					  $productOption[$value->getOptionsId()] = $value->getProductsOptionValue()->getProductsOptionsValuesId();
					}
					//dump($productOption); exit;
				}else{ break; }
			}
			if($flag == 1){ //if flag=1, add to cart
				//remove product from wishlist
 				$curr_Wishlist = $userService->getUser()->getCustomersNotes();
				$mod_WishList = array_diff($curr_Wishlist, array($product_id)); 
				$retVal = $userService->setCustomerWishlist($mod_WishList);
				if($retVal){
				  $cartService->add( $product_id, 1, $productOption ); $flag = 0; 
				  $redirect = $this->redirectToRoute('cart_page');
            	  $redirect->headers->setCookie($cartService->getCartCookie());
            	  $redirect->headers->setCookie($cartService->getCountryCookie());
            	  $redirect->headers->setCookie($userService->getUserCookie()); 

				  return $redirect;
				}else{
		          $redirect = $this->redirectToRoute('my_account_page');
				  $this->addFlash('error','Product Could Not Be Added To Cart');
            	  $redirect->headers->setCookie($cartService->getCartCookie());
            	  $redirect->headers->setCookie($cartService->getCountryCookie());
            	  $redirect->headers->setCookie($userService->getUserCookie());

				  return $redirect;
				}
			} else { //if flag=0, redirect to product page
				//get product slug
				$productSlug = $pageService->attachProductsSlugForUrl(array($wishlistProduct));
				//remove product from wishlist
 				$curr_Wishlist = $userService->getUser()->getCustomersNotes();
				$mod_WishList = array_diff($curr_Wishlist, array($product_id)); 
				$retVal = $userService->setCustomerWishlist($mod_WishList);
				if($retVal){
					$redirect = $this->redirectToRoute('product_page_by_slug', ['categorySlug' => $pageService->getConfiguration()->determineProductCategory($wishlistProduct), 
																			'productId' => $product_id, 'productSlug' => $productSlug[0]->getSlug() ] );
            		$redirect->headers->setCookie($cartService->getCartCookie());
            		$redirect->headers->setCookie($cartService->getCountryCookie());

					return $redirect;
				} else {
		          $redirect = $this->redirectToRoute('my_account_page');
				  $this->addFlash('error','Product Could Not Be Added To Cart');
            	  $redirect->headers->setCookie($cartService->getCartCookie());
            	  $redirect->headers->setCookie($cartService->getCountryCookie());
            	  $redirect->headers->setCookie($userService->getUserCookie());

				  return $redirect;
				}
			}
		}

        $cartInfo = $cartService->getCart(); 
        $orders = $pageService->getOrders($userService->getUser()->getCustomersId()); 

		//generate wishlist
		if( $userService->getUser() !== null ){ 
			$wishlists = $userService->getUser()->getCustomersNotes(); $wishlist_products = array();
            foreach($wishlists as $wpId){
              if(empty($wpId)) { continue; }
              elseif(!is_numeric($wpId)){ continue; }
			  else{  
				$wp_product = $pageService->getProduct($wpId);
				if($wp_product != null){ array_push($wishlist_products, $wp_product); }
			  }
			}
		} 
		else { $wishlist_products = array(); } 

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        $response = $this->render($pageService->getConfiguration()->getName().'/my_account.html.twig', [
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'customerform' => $customerForm->createView(),
			'changepasswordform' => $changePasswordForm->createView(),
            'page_name' => 'my_account_page',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'orders' => $orders,
			'wishlists' => $wishlist_products,
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }

    /**
     * @Route("/login", name="login_page")
     * @param PageService $pageService
     * @param Request $request
     * @param CartService $cartService
     * @return Response
     * @throws \Exception
     */
    public function login(PageService $pageService, Request $request, CartService $cartService, UserService $userService): Response
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));
        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($cookieCountry);

        $cartInfo = $cartService->getCart();

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        $loginForm = $this->getLoginForm();
        $registerForm = $this->getRegisterForm();
        $forgottenPasswordForm = $this->getForgottenPasswordForm();

        $response = $this->render($pageService->getConfiguration()->getName().'/login.html.twig', [
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'page_name' => 'my_account_page',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'login_form' => $loginForm->createView(),
            'register_form' => $registerForm->createView(),
            'forgotten_password_form' => $forgottenPasswordForm->createView(),
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }

    /**
     * @Route("/register", name="register_page")
     * @param PageService $pageService
     * @param Request $request
     * @param CartService $cartService
     * @return Response
     * @throws \Exception
     */
    public function register(PageService $pageService, Request $request, CartService $cartService, UserService $userService): Response
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));
        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($cookieCountry);

        $cartInfo = $cartService->getCart();

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        $loginForm = $this->getLoginForm();
        $registerForm = $this->getRegisterForm();
        $forgottenPasswordForm = $this->getForgottenPasswordForm();

        $response = $this->render($pageService->getConfiguration()->getName().'/register.html.twig', [
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'page_name' => 'my_account_page',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'login_form' => $loginForm->createView(),
            'register_form' => $registerForm->createView(),
            'forgotten_password_form' => $forgottenPasswordForm->createView(),
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }



    /**
     * @Route("/reviews", name="reviews_page")
     * @param PageService $pageService
     * @param Request $request
     * @param CartService $cartService
     * @param UserService $userService
     * @return Response
     * @throws \Exception
     */
    public function reviews(PageService $pageService, Request $request, CartService $cartService, UserService $userService): Response
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));
        $cartService->setCookieCountry($cookieCountry);

        $addToCartForm = $this->createFormBuilder(null, ['allow_extra_fields' => true])
            ->setAction($this->generateUrl('reviews_page'))
            ->setMethod('POST')
            ->getForm();

        $addToCartForm->handleRequest($request);
       // echo $addToCartForm->isSubmitted(); echo $addToCartForm->isValid();
        if ($addToCartForm->isSubmitted() ) {
            $data = $addToCartForm->getExtraData();
            $productId = $data['ProductId'];
            $cartService->add($productId, $data['Quantity'], $data['ProductOption'] ?? []);
           
            $redirect = $this->redirectToRoute('cart_page');
            $redirect->headers->setCookie($cartService->getCartCookie());
            $redirect->headers->setCookie($cartService->getCountryCookie());

            return $redirect;
        }            

        $cartInfo = $cartService->getCart();

        $page = $request->get('page', 1) - 1;
        $start = $page * $pageService->getConfiguration()->getNumProductsPerPage();

        $reviewsInfo = $pageService->reviews($start, $pageService->getConfiguration()->getNumProductsPerPage()); 

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        $response = $this->render($pageService->getConfiguration()->getName().'/reviews.html.twig', [
            'form' => $addToCartForm->createView(),
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'page_name' => 'my_account_page',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'reviews' => $reviewsInfo['results'],
            'count' => $reviewsInfo['count'],
            'pages' => ceil($reviewsInfo['count'] / $pageService->getConfiguration()->getNumProductsPerPage()),
            'page' => $page + 1,
            'visible_pages' => $pageService->getVisiblePages($page + 1, ceil($reviewsInfo['count'] / $pageService->getConfiguration()->getNumProductsPerPage())),
            'user' => $userService->getUser()
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }

    /**
     * @Route("/reviews/{productSlug}-pr{productId}.html", name="product_reviews_page", requirements={"productId"="\d+", "productSlug"=".+"})
     * @param string $productSlug
     * @param int $productId
     * @param PageService $pageService
     * @param Request $request
     * @param CartService $cartService
     * @return Response
     * @throws \Exception
     */
    public function reviewsForProduct(string $productSlug, int $productId, PageService $pageService, Request $request, CartService $cartService, UserService $userService): Response
    {

        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));
        $cartService->setCookieCountry($cookieCountry);


        $addToCartForm = $this->createFormBuilder(null, ['allow_extra_fields' => true])
            ->setAction($this->generateUrl('product_reviews_page', ['productId' => $productId, 'productSlug' => $productSlug]))
            ->setMethod('POST')
            ->getForm();

       

        $addToCartForm->handleRequest($request);

        if ($addToCartForm->isSubmitted() && $addToCartForm->isValid()) {
            $data = $addToCartForm->getExtraData();
            $cartService->add($productId, $data['Quantity'], $data['ProductOption'] ?? []);
           

            $redirect = $this->redirectToRoute('cart_page');
            $redirect->headers->setCookie($cartService->getCartCookie());
            $redirect->headers->setCookie($cartService->getCountryCookie());

            return $redirect;
        }


        $cartInfo = $cartService->getCart();

        $page = $request->get('page', 1) - 1;
        $start = $page * $pageService->getConfiguration()->getNumProductsPerPage();

        $reviewsInfo = $pageService->productReviews($productId, $start, $pageService->getConfiguration()->getNumProductsPerPage());

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        $response = $this->render($pageService->getConfiguration()->getName().'/product_reviews.html.twig', [
            'form' => $addToCartForm->createView(),
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'page_name' => 'my_account_page',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'reviews' => $reviewsInfo['results'],
            'count' => $reviewsInfo['count'],
            'pages' => ceil($reviewsInfo['count'] / $pageService->getConfiguration()->getNumProductsPerPage()),
            'page' => $page + 1,
            'visible_pages' => $pageService->getVisiblePages($page + 1, ceil($reviewsInfo['count'] / $pageService->getConfiguration()->getNumProductsPerPage())),
            'user' => $userService->getUser(),
            'product' => $reviewsInfo['product'],
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }

    /**
     * @Route("/write-review", name="write_review_page")
     * @param PageService $pageService
     * @param Request $request
     * @param CartService $cartService
     * @return Response
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function writeReview(PageService $pageService, Request $request, CartService $cartService, ReviewService $reviewService, UserService $userService): Response
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));
        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($cookieCountry);

        $cartInfo = $cartService->getCart();

        $productId = $request->get('product_id', 0);
        $product = $pageService->writeReview($productId);

        $form = $this->createFormBuilder(null, ['allow_extra_fields' => true])
            ->add('title', TextType::class, [])
            ->add('text', TextareaType::class)
            ->setAction($this->generateUrl('write_review_page', ['product_id' => $productId]))
            ->setMethod('POST')
            ->setAttribute('name', 'write_review_form')
            ->getForm();

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid() && $productId) {
            $data = $form->getData();
            $extraData = $form->getExtraData();

            $captcha = $request->get('g-recaptcha-response');
            if ('' !== $captcha) {
                $client = new \GuzzleHttp\Client(['base_uri' => 'https://www.google.com/recaptcha/api/']);
                $response = $client->request(Request::METHOD_POST, 'siteverify', [
                    'form_params' => [
                        'secret' => '6LehDlQUAAAAAHqHrmz31Qnc5jXzeHmp_v8phNMN',
                        'response' => $captcha,
                        'remoteip' => $request->getClientIp(),
                    ]
                ]);
                $response = json_decode((string)$response->getBody(), true);

                if ($response['success'] === true) {
                    $reviewService->saveReview($productId, $data['title'], $data['text'], $extraData['rating'] ?? 5);

                    $response = $this->render(
                        $pageService->getConfiguration()->getName().'/write_review_success.html.twig',
                        [
                            'cart_products' => $cartInfo['products'],
                            'cart_subtotal' => $cartInfo['subtotal'],
                            'cart_shipping' => $cartInfo['shipping'],
                            'page_name' => 'write_review_page',
                            'meta_title' => $metaTitle,
                            'meta_description' => $metaDescription,
                            'meta_keywords' => $metaKeywords,
                            'header' => $header,
                            'product_id' => $productId,
                            'product' => $product,
                            'user' => $userService->getUser(),
                        ]
                    );
                    $response->headers->setCookie($cartService->getCartCookie());
                    $response->headers->setCookie($cartService->getCountryCookie());
                    $response->headers->setCookie($userService->getUserCookie());

                    return $response;
                }
            }
        }

        $response = $this->render($pageService->getConfiguration()->getName().'/write_review.html.twig', [
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'page_name' => 'write_review_page',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'form' => $form->createView(),
            'product_id' => $productId,
            'product' => $product,
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }

    /**
     * @Route("/my-account-history", name="my_account_history_page")
     * @param PageService $pageService
     * @param Request $request
     * @param CartService $cartService
     * @return Response
     * @throws \Exception
     */
    public function myAccountHistory(PageService $pageService, Request $request, CartService $cartService, UserService $userService): Response
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));
        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($cookieCountry);

        $cartInfo = $cartService->getCart();

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        $response = $this->render($pageService->getConfiguration()->getName().'/my_account_history.html.twig', [
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'page_name' => 'my_account_page',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        // tmp
        $redirect = $this->redirectToRoute('login_page');
        $redirect->headers->setCookie($cartService->getCartCookie());
        $redirect->headers->setCookie($cartService->getCountryCookie());
        return $redirect;

        return $response;
    }

    /**
     * @Route("/update-country", name="update_country")
     * @param PageService $pageService
     * @param Request $request
     * @param CartService $cartService
     * @param UserService $userService
     * @return RedirectResponse
     */
    public function updateCountry(PageService $pageService, Request $request, CartService $cartService, UserService $userService): RedirectResponse
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));
        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($cookieCountry);
        $sessionCountry = $this->get('session')->get('country', $cookieCountry);

        $countries = $pageService->getCountries();
        $choices = [];
        foreach ($countries as $country) {
            $choices[$country->getCountriesName()] = $country->getCountriesIsoCode2();
        }

        $updateCountryForm = $this->getUpdateCountryForm($choices, $sessionCountry);

        $updateCountryForm->handleRequest($request);

        if ($updateCountryForm->isSubmitted() && $updateCountryForm->isValid()) {
            $data = $updateCountryForm->getData();
            $cartService->setCookieCountry($data['country']);
            $this->get('session')->set('country', $data['country']);
        }

        $redirect = $this->redirectToRoute('cart_page');
        $redirect->headers->setCookie($cartService->getCartCookie());
        $redirect->headers->setCookie($cartService->getCountryCookie());
        $redirect->headers->setCookie($userService->getUserCookie());

        return $redirect;
    }

    /**
     * @Route("/update-quantity", name="update_quantity")
     * @param Request $request
     * @param CartService $cartService
     * @return RedirectResponse
     */
    public function updateQuantity(Request $request, CartService $cartService, UserService $userService): RedirectResponse
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));
        $country = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($country);

        $updateForm = $this->getUpdateQuantityForm();

        $updateForm->handleRequest($request);

        if ($updateForm->isSubmitted() && $updateForm->isValid()) {
            $data = $updateForm->getData();
            if ($data['action'] === 'update') {
                $cartService->update($data['idx'], $data['quantity']);
            }
        }

        $redirect = $this->redirectToRoute('cart_page');
        $redirect->headers->setCookie($cartService->getCartCookie());
        $redirect->headers->setCookie($cartService->getCountryCookie());
        $redirect->headers->setCookie($userService->getUserCookie());

        return $redirect;
    }

    /**
     * @Route("/remove-product", name="remove_product")
     * @param Request $request
     * @param CartService $cartService
     * @return RedirectResponse
     */
    public function removeProduct(Request $request, CartService $cartService,UserService $userService): RedirectResponse
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));
        $country = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($country);

        $removeForm = $this->getRemoveProductForm();

        $removeForm->handleRequest($request);

        if ($removeForm->isSubmitted() && $removeForm->isValid()) {
            $data = $removeForm->getData();
            if ($data['action'] === 'remove') {
                $cartService->remove($data['idx']);
            }
        }

        $redirect = $this->redirectToRoute('cart_page');
        $redirect->headers->setCookie($cartService->getCartCookie());
        $redirect->headers->setCookie($cartService->getCountryCookie());
        $redirect->headers->setCookie($userService->getUserCookie());

        return $redirect;
    }

    /**
     * @Route("/cart", name="cart_page")
     * @param PageService $pageService
     * @param Request $request
     * @param CartService $cartService
     * @return Response
     * @throws \Exception
     */
    public function cart(PageService $pageService, Request $request, CartService $cartService, UserService $userService): Response
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));
        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $sessionCountry = $this->get('session')->get('country', $cookieCountry);
        $cartService->setCookieCountry($sessionCountry);

        $countries = $pageService->getCountries();
        $choices = [];
        foreach ($countries as $country) {
            $choices[$country->getCountriesName()] = $country->getCountriesIsoCode2();
        }

        $updateCountryForm = $this->getUpdateCountryForm($choices, $sessionCountry);

        $updateForm = $this->getUpdateQuantityForm();
        $updateFormCpy = clone $updateForm;

        $removeForm = $this->getRemoveProductForm();
        $removeFormCpy = clone $removeForm;

        $cartInfo = $cartService->getCart();

        $forms = [];
        foreach ($cartInfo['products'] as $idx => $cartProduct) {
            $updateCartform = $updateFormCpy;
            $updateCartform = $updateCartform->add('idx', HiddenType::class, ['data' => $idx])
                ->add('quantity', IntegerType::class, [
                    'data' => $cartProduct['quantity'],
                    'label' => false
                ]);
            $forms[$idx]['update'] = $updateCartform->createView();

            $removeCartform = $removeFormCpy;
            $removeCartform = $removeCartform->add('idx', HiddenType::class, ['data' => $idx]);
            $forms[$idx]['remove'] = $removeCartform->createView();

            $cartInfo['products'][$idx]['product'] = $pageService->attachProductsSlugForUrl([$cartInfo['products'][$idx]['product']])[0];
            $cartInfo['products'][$idx]['product'] = $pageService->attachCategoriesForUrl([$cartInfo['products'][$idx]['product']])[0];
        }

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        $response = $this->render($pageService->getConfiguration()->getName().'/cart.html.twig', [
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'forms' => $forms,
            'update_country_form' => $updateCountryForm->createView(),
            'page_name' => 'cart_page',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }

    /**
     * @Route("/search", name="search_page")
     * @param PageService $pageService
     * @param CartService $cartService
     * @param Request $request
     * @param UserService $userService
     * @return Response
     * @throws \Exception
     * @throws NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function search(PageService $pageService, CartService $cartService, Request $request, UserService $userService): Response
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));
        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($cookieCountry);

        $cartInfo = $cartService->getCart();

        $page = $request->get('page', 1) - 1;
        $start = $page * $pageService->getConfiguration()->getNumProductsPerPage();
        $query = $request->get('w', ''); 
        if (empty($query)) {
            $query = $request->get('q', ''); 
            
        }
        $searchInfo = $pageService->search($query, $start, $pageService->getConfiguration()->getNumProductsPerPage());
        

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        $response = $this->render($pageService->getConfiguration()->getName().'/search.html.twig', [
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'page_name' => 'search_home',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'products' => $searchInfo['results'],
            'count' => $searchInfo['count'],
            'pages' => ceil($searchInfo['count'] / $pageService->getConfiguration()->getNumProductsPerPage()),
            'page' => $page + 1,
            'query' => $query,
            'visible_pages' => $pageService->getVisiblePages($page + 1, ceil($searchInfo['count'] / $pageService->getConfiguration()->getNumProductsPerPage())),
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }


    /**
     * @Route("/advanced_search", name="advanced_search_page")
     * @param PageService $pageService
     * @param CartService $cartService
     * @param Request $request
     * @param UserService $userService
     * @return Response
     * @throws \Exception
     * @throws NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function advanced_search(PageService $pageService, CartService $cartService, Request $request, UserService $userService): Response
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));
        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($cookieCountry);

        $brandsForm = $this->getBrandsForm(0, $pageService);
          $brandsForm->handleRequest($request);
       $brand = $request->get('brand', 0);
       if ($brandsForm->isSubmitted() && $brandsForm->isValid()) {
           $data = $brandsForm->getData();
           if ((int)$data['brand'] !== 0) {
               $brand = (int)$data['brand'];
           }
       }
       

        $cartInfo = $cartService->getCart();

        $page = $request->get('page', 1) - 1;
        $start = $page * $pageService->getConfiguration()->getNumProductsPerPage();
        $query = $request->get('w', ''); 
        if (empty($query)) {
            $query = $request->get('q', ''); 
            
        }
        $searchInfo = $pageService->search($query, $start, $pageService->getConfiguration()->getNumProductsPerPage());
        if($brand != 0){ $searchInfo = $pageService->brand($brand, 0, 10000); }  

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';



        $response = $this->render($pageService->getConfiguration()->getName().'/advanced_search.html.twig', [
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'page_name' => 'advanced_search_home',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'products' => $searchInfo['results'],
            'count' => $searchInfo['count'],
            'pages' => ceil($searchInfo['count'] / $pageService->getConfiguration()->getNumProductsPerPage()),
            'page' => $page + 1,
            'query' => $query,
            'visible_pages' => $pageService->getVisiblePages($page + 1, ceil($searchInfo['count'] / $pageService->getConfiguration()->getNumProductsPerPage())),
            'user' => $userService->getUser(),
            'brand_form' => $brandsForm->createView(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }





    /**
     * @Route("/paypal/notify-express", name="paypal_express_notify_page")
     * @param Request $request
     * @param PageService $pageService
     * @param CartService $cartService
     * @param OrderService $orderService
     * @param LoggerInterface $logger
     * @param RouterInterface $router
     * @return RedirectResponse
     * @throws \Exception
     * @throws \Paypal\Exception\PPConnectionException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function paypalNotifyExpress(Request $request, PageService $pageService, CartService $cartService, OrderService $orderService, LoggerInterface $logger, RouterInterface $router, UserService $userService): RedirectResponse
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));

        $cartInfo = $cartService->getCart();

        $token = $request->get('token');

        $getExpressCheckoutDetailsRequest = new GetExpressCheckoutDetailsRequestType($token);
        $getExpressCheckoutReq = new GetExpressCheckoutDetailsReq();

        $getExpressCheckoutReq->GetExpressCheckoutDetailsRequest = $getExpressCheckoutDetailsRequest;

        $paypalService = new PayPalAPIInterfaceServiceService(
            [
                'acct1.UserName' => $this->getParameter('paypal.username'),
                'acct1.Password' => $this->getParameter('paypal.password'),
                'acct1.Signature' => $this->getParameter('paypal.signature'),
                'mode' => $this->getParameter('paypal.mode'),
            ]
        );

        $getECResponse = $paypalService->GetExpressCheckoutDetails($getExpressCheckoutReq);

        if (null !== $getECResponse) {
            if ('Success' === $getECResponse->Ack) {
                $logger->info('GetExpressCheckoutDetails Request response: '.$request->getContent());


                $orderTotal = new BasicAmountType();
                $orderTotal->currencyID = 'AUD';
                $orderTotal->value = $cartInfo['subtotal'] + $cartInfo['shipping'];
                $paymentDetails= new PaymentDetailsType();
                $paymentDetails->OrderTotal = $orderTotal;
                $paymentDetails->NotifyURL = $router->generate('paypal_ipn_notify_page', [], RouterInterface::ABSOLUTE_URL);

                $DoECRequestDetails = new DoExpressCheckoutPaymentRequestDetailsType();
                $DoECRequestDetails->PayerID = $getECResponse->GetExpressCheckoutDetailsResponseDetails->PayerInfo->PayerID;
                $DoECRequestDetails->Token = $token;
                $DoECRequestDetails->PaymentAction = 'Sale';
                $DoECRequestDetails->PaymentDetails[] = $paymentDetails;
                $DoECRequest = new DoExpressCheckoutPaymentRequestType();
                $DoECRequest->DoExpressCheckoutPaymentRequestDetails = $DoECRequestDetails;
                $DoECReq = new DoExpressCheckoutPaymentReq();
                $DoECReq->DoExpressCheckoutPaymentRequest = $DoECRequest;

                try {
                    $DoECResponse = $paypalService->DoExpressCheckoutPayment($DoECReq);
                } catch (\Paypal\Exception\PPConnectionException $e) {
                    $logger->critical('Paypal GetExpressCheckoutDetails exception: '.$e->getMessage().'; '.$e->getCode().'; '.$e->getData());
                    throw $e;
                }

                if (null !== $DoECResponse ) {
                    if ('Success' === $DoECResponse->Ack) {
                        $orderId = $getECResponse->GetExpressCheckoutDetailsResponseDetails->InvoiceID;

                        $orderService->updateOrderToPaid((int)$orderId);

                        $orderService->sendEmail(
                            [
                                'email' => $getECResponse->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Payer,
                                'first_name' => $getECResponse->GetExpressCheckoutDetailsResponseDetails->PayerInfo->PayerName->FirstName,
                                'last_name' => $getECResponse->GetExpressCheckoutDetailsResponseDetails->PayerInfo->PayerName->LastName,
                                'address1' => $getECResponse->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Address->Street1,
                                'address2' => $getECResponse->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Address->Street2,
                                'city' => $getECResponse->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Address->CityName,
                                'zip' => $getECResponse->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Address->PostalCode,
                                'state' => $getECResponse->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Address->StateOrProvince,
                                'country' => $getECResponse->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Address->CountryName,
                            ],
                            $cartInfo,
                            'paypal',
                            $pageService
                        );

                        $redirect = $this->redirectToRoute('checkout_success_page');
                        $redirect->headers->setCookie($cartService->getCountryCookie());
//                $redirect->headers->clearCookie(CartService::CART_COOKIE_NAME);

                        return $redirect;

                    } else {
                        $logger->error('Paypal DoExpressCheckoutPayment error: '.$DoECResponse->toXMLString());
                    }
                } else {
                    $logger->error('Paypal DoExpressCheckoutPayment error: '.$request->getContent());
                }

            } else {
                $logger->error('Paypal GetExpressCheckoutDetails error: '.$getECResponse->toXMLString());
            }
        } else {
            $logger->error('No response from paypal: '.$request->getContent());
        }

        $redirect = $this->redirectToRoute('checkout_payment_page', ['errors' => 'Paypal Express Error: ']);
        $redirect->headers->setCookie($cartService->getCartCookie());
        $redirect->headers->setCookie($cartService->getCountryCookie());
        $redirect->headers->setCookie($userService->getUserCookie());

        return $redirect;
    }

    /**
     * @Route("/do/login", name="do_login_page")
     * @param Request $request
     * @param UserService $userService
     * @param CartService $cartService
     * @return RedirectResponse
     */
    public function doLogin(Request $request, UserService $userService, CartService $cartService): RedirectResponse
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));

        $loginForm = $this->getLoginForm();

        $loginForm->handleRequest($request);
         $googleVarify = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=6Lcjul8UAAAAAP1gM3L-zRPODy2jr49ohR8OnpIh&response='.$_POST['g-recaptcha-response'].'&remoteip='.$_SERVER['REMOTE_ADDR']);


                $googleBrake = json_decode($googleVarify,false);

        if ($loginForm->isSubmitted() && $loginForm->isValid() && $googleBrake->success == 1) {
            $data = $loginForm->getData();
            if ($userService->login($data['email'], $data['password'])) {
                $redirect = $this->redirectToRoute('my_account_page');
                $redirect->headers->setCookie($cartService->getCartCookie());
                $redirect->headers->setCookie($cartService->getCountryCookie());
                $redirect->headers->setCookie($userService->getUserCookie());

                return $redirect;
            }
        }

		$this->addFlash('error','Your User Id Or Password Is Wrong - Please Try Again');
		$rediectToLogin = $this->redirectToRoute('login_page'); 

        return $rediectToLogin ;
    }

    /**
     * @Route("/do/register", name="do_register_page")
     * @param Request $request
     * @param UserService $userService
     * @param CartService $cartService
     * @param PageService $pageService
     * @param \Swift_Mailer $mailer
     * @return RedirectResponse
     * @throws \Exception
     */
    public function doRegister(Request $request, UserService $userService, CartService $cartService, PageService $pageService, \Swift_Mailer $mailer, \Twig_Environment $twig): RedirectResponse
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));

        $registerForm = $this->getRegisterForm();

        $registerForm->handleRequest($request);

          //$googleVarify = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=6Lcjul8UAAAAAP1gM3L-zRPODy2jr49ohR8OnpIh&response='.$_POST['g-recaptcha-response'].'&remoteip='.$_SERVER['REMOTE_ADDR']);
          //$googleBrake = json_decode($googleVarify,false);
        //if ($registerForm->isSubmitted() && $registerForm->isValid() && $googleBrake->success == 1) { 
        if ($registerForm->isSubmitted() && $registerForm->isValid()) {
            $data = $registerForm->getData();
            if ($userService->register($data['email'], $data['password'], $data['first_name'], $data['last_name'], $data['dob'])) {

                $nCustomer = $userService->getUser(); $nCustomerID = $nCustomer->getCustomersId();

        $em = $this->getDoctrine()->getManager(); 
            $r_query = "INSERT INTO customers_info_retail (customers_info_id, customers_info_date_account_created) ";
            $r_query .= "VALUES ( ".(int)$nCustomerID. ", NOW() ) ";
            $statement = $em->getConnection()->prepare($r_query); $statement->execute(); 

                $html = $twig->render($pageService->getConfiguration()->getName().'/welcome_email.html.twig', [
                    'form_customer' => $data,
                ]);
                $message = (new \Swift_Message('Your account at '.$pageService->getConfiguration()->getDomainName()))
                    ->setFrom($pageService->getConfiguration()->getEmailFrom(), $pageService->getConfiguration()->getEmailFromName())
                    ->setTo($data['email'], $data['first_name'].' '.$data['last_name'])
                    ->setBcc($pageService->getConfiguration()->getEmailFrom(), $pageService->getConfiguration()->getEmailFromName())
                    ->setBody($html, 'text/html');
                $mailer->send($message);

                $redirect = $this->redirectToRoute('my_account_page');
                $redirect->headers->setCookie($cartService->getCartCookie());
                $redirect->headers->setCookie($cartService->getCountryCookie());
                $redirect->headers->setCookie($userService->getUserCookie());

                return $redirect;
            }
        }

        //$rediectToLogin = $this->redirectToRoute('login_page');
        //$rediectToLogin->setContent('Error: User already exists - Please Login');

		$this->addFlash('error','You Already Have An Account With Us With Same Email Id  - Please Login');
		$rediectToRegister = $this->redirectToRoute('register_page'); 

        return $rediectToRegister;
    }

    /**
     * @Route("/do/forgot-password", name="do_forgot_password_page")
     * @param Request $request
     * @param UserService $userService
     * @param CartService $cartService
     * @param PageService $pageService
     * @param \Swift_Mailer $mailer
     * @return RedirectResponse
     * @throws \Exception
     */
    public function doForgotPassword(Request $request, UserService $userService, CartService $cartService, PageService $pageService, \Swift_Mailer $mailer, \Twig_Environment $twig): RedirectResponse
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));

        $passwordForgotForm = $this->getForgottenPasswordForm();

        $passwordForgotForm->handleRequest($request);

         $googleVarify = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=6Lcjul8UAAAAAP1gM3L-zRPODy2jr49ohR8OnpIh&response='.$_POST['g-recaptcha-response'].'&remoteip='.$_SERVER['REMOTE_ADDR']);


                $googleBrake = json_decode($googleVarify,false);

        if ($passwordForgotForm->isSubmitted() && $passwordForgotForm->isValid() && $googleBrake->success == 1) {
            $data = $passwordForgotForm->getData();
            $newPassword = $userService->resetPassword($data['email']);
            if (null !== $newPassword) {
                $name = $userService->getCustomer()->getCustomersFirstname().' '.$userService->getCustomer()->getCustomersLastname();
                $html = $twig->render(
                    $pageService->getConfiguration()->getName().'/password_reset_email.html.twig',
                    [
                        'password' => $newPassword,
                        'name' => $name,
                    ]
                );
                $message = (new \Swift_Message(
                    'Your new password at '.$pageService->getConfiguration()->getDomainName()
                ))
                    ->setFrom(
                        $pageService->getConfiguration()->getEmailFrom(),
                        $pageService->getConfiguration()->getEmailFromName()
                    )
                    ->setTo($data['email'], $name)
                    ->setBcc(
                        $pageService->getConfiguration()->getEmailFrom(),
                        $pageService->getConfiguration()->getEmailFromName()
                    )
                    ->setBody($html, 'text/html');
                $mailer->send($message);

                $redirect = $this->redirectToRoute('info_page',['pageName' =>'password_resent']);
                $redirect->headers->setCookie($cartService->getCartCookie());
                $redirect->headers->setCookie($cartService->getCountryCookie());
                $redirect->headers->setCookie($userService->getUserCookie());

                return $redirect;
            }
        }

        return $this->redirectToRoute('login_page');
    }

    /**
     * @Route("/do/logout", name="do_logout_page")
     * @param Request $request
     * @param UserService $userService
     * @param CartService $cartService
     * @return RedirectResponse
     */
    public function doLogout(Request $request, UserService $userService, CartService $cartService)
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie('');

        $redirect = $this->redirectToRoute('login_page');
        $redirect->headers->setCookie($cartService->getCartCookie());
        $redirect->headers->setCookie($cartService->getCountryCookie());
        $redirect->headers->setCookie($userService->getUserCookie());

        return $redirect;
    }

    /**
     * @Route("/paypal/notify-ipn", name="paypal_ipn_notify_page")
     * @param Request $request
     * @param PageService $pageService
     * @param CartService $cartService
     * @param OrderService $orderService
     * @param LoggerInterface $logger
     * @return void
     * @throws \Exception
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function paypalNotifyIpn(Request $request, PageService $pageService, CartService $cartService, OrderService $orderService, LoggerInterface $logger)
    {
        $paypalIpn = new PPIPNMessage('', [
            'acct1.UserName' => $this->getParameter('paypal.username'),
            'acct1.Password' => $this->getParameter('paypal.password'),
            'acct1.Signature' => $this->getParameter('paypal.signature'),
            'mode' => $this->getParameter('paypal.mode'),
        ]);

        if (!$paypalIpn->validate()) {
            $logger->error('Unable to verify PayPal IPN: '.$request->getContent());
            throw new \Exception('Unable to verify PayPal IPN: '.$request->getContent());
            die(200);
        }

        if ($paypalIpn->getRawData()['payment_status'] !== 'Completed') {
            $logger->error('PayPal IPN Payment was not completed: '.$request->getContent());
            throw new \Exception('PayPal IPN Payment was not completed: '.$request->getContent());
            die(200);
        }

        if (!isset($paypalIpn->getRawData()['invoice_id']) && !isset($paypalIpn->getRawData()['invoice'])) {
            $logger->error('Missing invoice ID: '.$request->getContent());
            throw new \Exception('Missing invoice ID: '.$request->getContent());
            die(200);
        }

        $cartInfo = [];
        if (isset($paypalIpn->getRawData()['invoice_id'])) {
            $orderId = $paypalIpn->getRawData()['invoice_id'];
        } else {
            $orderId = $paypalIpn->getRawData()['invoice'];
        }

        if (isset($paypalIpn->getRawData()['item_number'])) {
            $cartInfo[] = [
                'product_id' => $paypalIpn->getRawData()['item_number'],
                'quantity' => $paypalIpn->getRawData()['quantity'],
            ];
        }

        for ($i = 1; $i <= 10; $i++) {
            if (isset($paypalIpn->getRawData()['item_number_'.$i])) {
                $cartInfo[] = [
                    'product_id' => $paypalIpn->getRawData()['item_number_'.$i],
                    'quantity' => $paypalIpn->getRawData()['quantity_'.$i],
                ];
            }
        }

        $cartInfo['subtotal'] = $paypalIpn->getRawData()['payment_gross'] - $paypalIpn->getRawData()['shipping'];
        $cartInfo['shipping'] = $paypalIpn->getRawData()['shipping'];
        $cartInfo['total'] = $paypalIpn->getRawData()['payment_gross'];

        $orderService->updateOrderToPaid((int)$orderId);

        $orderService->sendEmail(
            [
                'email' => $paypalIpn->getRawData()['payer_email'],
                'first_name' => $paypalIpn->getRawData()['first_name'],
                'last_name' => $paypalIpn->getRawData()['last_name'],
                'address1' => $paypalIpn->getRawData()['address_street'],
                'address2' => '',
                'city' => $paypalIpn->getRawData()['address_city'],
                'zip' => $paypalIpn->getRawData()['address_zip'],
                'state' => $paypalIpn->getRawData()['address_state'],
                'country' => $paypalIpn->getRawData()['address_country_code'],
            ],
            $cartInfo,
            'paypal',
            $pageService
        );

    }

    /**
     * @Route("/page/{pageName}", name="info_page")
     * @param string $pageName
     * @param PageService $pageService
     * @param CartService $cartService
     * @param Request $request
     * @param UserService $userService
     * @return Response
     * @throws NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function infoPage(
        string $pageName,
        PageService $pageService,
        CartService $cartService,
        Request $request,
        UserService $userService
    ): Response {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));

        $cartInfo = $cartService->getCart();

        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($cookieCountry);

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        $contents = [];

        if ($pageName === 'sitemap') {
            $contents['sitemap'] = $pageService->sitemap();
        } elseif ($pageName === 'contact_us') {
            $form = $this->createFormBuilder(null)
                ->add('name', TextType::class, [])
                ->add('email', EmailType::class)
                ->add('phone', TextType::class)
                ->add('message', TextareaType::class)
                ->add('send', SubmitType::class, ['label' => 'Send'])
                ->setAction($this->generateUrl('info_page', ['pageName' => 'contact_us']))
                ->setMethod('POST')
                ->setAttribute('name', 'contact_form')
                ->getForm();

            $form->handleRequest($request);

       

            $googleVarify = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=6Lcjul8UAAAAAP1gM3L-zRPODy2jr49ohR8OnpIh&response='.$_POST['g-recaptcha-response'].'&remoteip='.$_SERVER['REMOTE_ADDR']);


                $googleBrake = json_decode($googleVarify,false);
            //print_r($googleBrake);
            //exit;
            if ($form->isSubmitted() && $form->isValid() && $googleBrake->success == 1) {
                //echo '<pre>';
                //print_r($form['name']);exit;
                $pageService->contactUsEmail($form);

                $redirect = $this->redirectToRoute('info_page', ['pageName' => 'contact_us_success']);
                $redirect->headers->setCookie($cartService->getCartCookie());
                $redirect->headers->setCookie($cartService->getCountryCookie());

                return $redirect;
            }

            $contents['form'] = $form->createView();
        } elseif ($pageName === 'brands') {
            $contents['brands'] = $pageService->brands();
        } elseif ($pageName === 'delivery_locations') {
            $contents['countries'] = $pageService->deliveryLocations();
        } elseif ($pageName === 'delivery_locations_australia') {
            if($request->query->count() == 0){ 
                $state=''; $city=''; $region=''; $contents['locations'] = $pageService->deliveryLocationsAustralia($state, $city, $region);
            }
            if($request->query->count() == 1){ 
                $state=$request->query->get('state'); $city=''; $region='';
                $contents['locations'] = $pageService->deliveryLocationsAustralia($state, $city, $region);
            }
            if($request->query->count() == 2){ 
                $state=$request->query->get('state'); 
                $city=$request->query->get('city');$region='';
                $contents['locations'] = $pageService->deliveryLocationsAustralia($state, $city, $region);
            }
            if($request->query->count() == 3){ 
                $state=$request->query->get('state'); 
                $city=$request->query->get('city');
                $region=$request->query->get('region');
                $contents['locations'] = $pageService->deliveryLocationsAustralia($state, $city, $region);
            }
        }
        elseif ($pageName === 'discontinued_adult_products') {
            $page = $request->get('page', 1) - 1;
            $start = $page * $pageService->getConfiguration()->getNumProductsPerPage();
            $result = $pageService->discontinued($start, $pageService->getConfiguration()->getNumProductsPerPage());
            $contents['products'] = $result['results'];
            $contents['page'] = $page;
            $contents['pages'] = ceil($result['count'] / $pageService->getConfiguration()->getNumProductsPerPage());
            $contents['visible_pages'] = $pageService->getVisiblePages($page + 1, ceil($result['count'] / $pageService->getConfiguration()->getNumProductsPerPage()));
        }

        $response = $this->render($pageService->getConfiguration()->getName().'/'.$pageName.'.html.twig', [
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'page_name' => 'info_page',
            'info_page_name' => $pageName,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'contents' => $contents,
            'page_service' => $pageService,
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }

    /**
     * @Route("/checkout-success", name="checkout_success_page")
     * @param PageService $pageService
     * @param CartService $cartService
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function checkoutSuccess(PageService $pageService, CartService $cartService, Request $request, UserService $userService): Response
    {

	$cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));

        $cartInfo = $cartService->getCart(); //\Symfony\Component\VarDumper\VarDumper::dump($this->container); 

    //-------------------
    // afterpay checkout 
    //-------------------
    if( ($request->query->get('status') == 'SUCCESS') && ($request->query->get('orderToken') != NULL ) ){  
        $token = $request->query->get('orderToken');
        //--------------------------------------------------------
        //create payment via api -- /v1/payments/capture endpoint        
        //--------------------------------------------------------
        $afterpayClient = new \GuzzleHttp\Client(['base_uri' => 'https://api-sandbox.afterpay.com']); 
        $apikey = base64_encode('40351'.':'.'5287f73ac23c6199705e0c9310537df1017a5a3e19361069dc2578ced0ab99524adaf94e34e55a64e4ff6c47742a3fcf0c4b4df5e624490a81be65d34cbf25fc');
        $response = $afterpayClient->request(Request::METHOD_POST, '/v1/payments/capture', [ 
                        'headers' => [
                            'Authorization' => 'Basic '.$apikey,
                            'Content-Type' => 'application/json', 
                            'User-Agent' => 'AdultsmartAfterpayModule/1.0.0 (PHP/7.1.1; Merchant/40351)',
                            'Accept' => 'application/json' ],
                        'json' => [ 'token' => $token, ],  
                    ]);
        // response for /v1/orders 
        $afterpayResponse = json_decode((string)$response->getBody(), true); //dump($afterpayResponse); exit; 

        if( $afterpayResponse['status'] != 'APPROVED' ){ 
            dump($afterpayResponse); exit; 
            //payment not approved
            $this->addFlash('error','Payment Not Approved ');    
            $redirect = $this->redirectToRoute('cart_page');
            $redirect->headers->setCookie($cartService->getCartCookie());
            $redirect->headers->setCookie($cartService->getCountryCookie());
            $redirect->headers->setCookie($userService->getUserCookie());

            return $redirect;

        }else{
            //payment was successful, update order            
            $orderId = $afterpayResponse['merchantReference'];
            $oretailRepository = $this->container->get('App\Repository\OrderRetailRepository');
            $norder = $oretailRepository->find($orderId); $norder->setOrdersStatus(1);
            $entityManager = $this->container->get('doctrine.orm.default_entity_manager');
            $entityManager->flush();
        }

    } else { //response is not SUCCESS
        $this->addFlash('error','Payment Could Not Be Processed');
        $redirect = $this->redirectToRoute('cart_page');
        $redirect->headers->setCookie($cartService->getCartCookie());
        $redirect->headers->setCookie($cartService->getCountryCookie());
        $redirect->headers->setCookie($userService->getUserCookie());

        return $redirect;
    }


    //------------------
    // zippay checkout
    //------------------    
	if($request->query->get('checkoutId') !== null){ 
	  // Configure API key authorization: Authorization
	  //\zipMoney\Configuration::getDefaultConfiguration()->setApiKey('Authorization', 'yhd7bxotuh/EHxKuJp9yH09Rn/8Hbz1EcAmT9S62P9U='); 
	  \zipMoney\Configuration::getDefaultConfiguration()->setApiKey('Authorization', 'd1bdEnS8ocFPsW4foatlq0HgKAlqr520R1F6v2yYkCU=');
	  \zipMoney\Configuration::getDefaultConfiguration()->setApiKeyPrefix('Authorization', 'Bearer');
	  //\zipMoney\Configuration::getDefaultConfiguration()->setEnvironment('sandbox'); // Allowed values are  ( sandbox | production )
	  \zipMoney\Configuration::getDefaultConfiguration()->setEnvironment('production'); // Allowed values are  ( sandbox | production )
	  //\zipMoney\Configuration::getDefaultConfiguration()->setPlatform('Php/5.6'); // E.g. Magento/1.9.1.2	
	  try { 
	  	$id = $request->query->get('checkoutId'); $apiResult = $request->query->get('result'); 

		//if result approved, then update db
		if($apiResult == 'approved'){ 

                  $zippayCId = $request->query->get('customerId');
                  $api_instance = new \zipMoney\Api\CheckoutsApi();
                  $checkoutObj = $api_instance->checkoutsGet($id);
                  //echo '<pre>'; print_r($checkoutObj); echo '</pre>'; exit;
	
		  //create a charge object 
		  try { 
		     $api_instance = new \zipMoney\Api\ChargesApi();
		     //authority model 
		     $authorityData = array('type'=>'checkout_id', 'value'=>$checkoutObj->getId()); 
		     $authorityDetails = new \zipMoney\Model\Authority($authorityData); 
		     //charge order data 
		     $chargeOrderData = array('reference'=>$checkoutObj->getOrder()->getReference(), 'shipping'=>$checkoutObj->getOrder()->getShipping(), 
						 'items'=>$checkoutObj->getOrder()->getItems(), 'cart_reference'=>'' ); 
		     $chargeOrderDetails = new \zipMoney\Model\ChargeOrder($chargeOrderData);
		     //charge request data
		     $chargeRequestData = array('authority'=>$authorityDetails, 'reference'=>$checkoutObj->getOrder()->getReference(),
						'amount'=>$checkoutObj->getOrder()->getAmount(), 'currency'=>$checkoutObj->getOrder()->getCurrency(), 
						'capture'=>true, 'order'=>$chargeOrderDetails, 'metadata'=>'' );
		     $body = new \zipMoney\Model\CreateChargeRequest($chargeRequestData);  
		     $idempotency_key = $checkoutObj->getId(); 
		     //get the charge
		     $chargeObj = $api_instance->chargesCreate($body, $idempotency_key); 
		     //echo '<pre>'; print_r($chargeObj); echo '</pre>'; exit; 

	             //insert details into db
         	     $checkoutOrderId = $checkoutObj->getOrder()->getReference();
                     $oretailRepository = $this->container->get('App\Repository\OrderRetailRepository');
                     $norder = $oretailRepository->find($checkoutOrderId);
                     $norder->setOrdersStatus(1);
                     $entityManager = $this->container->get('doctrine.orm.default_entity_manager');
                     $entityManager->flush();

		  } catch (Exception $e) {
    		      echo 'Exception when calling ChargesApi->chargesCreate: ', $e->getMessage(), PHP_EOL;
		  }

	    } elseif($apiResult == 'cancelled'){ //dump($apiResult);

		    $redirect = $this->redirectToRoute('cart_page');
            	    $redirect->headers->setCookie($cartService->getCartCookie());
            	    $redirect->headers->setCookie($cartService->getCountryCookie());
		    $redirect->headers->setCookie($userService->getUserCookie());

            	    return $redirect;

		} else {

                    $redirect = $this->redirectToRoute('cart_page');
                    $redirect->headers->setCookie($cartService->getCartCookie());
                    $redirect->headers->setCookie($cartService->getCountryCookie());
		    $redirect->headers->setCookie($userService->getUserCookie());

                    return $redirect;
		} 

						
	  } catch (Exception $e) {
    		echo 'Exception when calling CheckoutsApi->checkoutsGet: ', $e->getMessage(), PHP_EOL;
	  } 

	}

//        $cleanCart = $request->get('c', 'false');
//        if ($cleanCart === 'true') {
//            $request->cookies->remove(CartService::CART_COOKIE_NAME);
//        }


        $sessionEmail = $this->get('session')->get('email');
        $sessionFname = $this->get('session')->get('first_name');
        $sessionLname = $this->get('session')->get('last_name');
        $sessionAddr1 = $this->get('session')->get('address1');
        $sessionAddr2 = $this->get('session')->get('address2');
        $sessionCity = $this->get('session')->get('city');
        $sessionZip = $this->get('session')->get('zip');
        $sessionState = $this->get('session')->get('state');
        $sessionBillingasshipping = $this->get('session')->get('billing_as_shipping');
        $sessionComments = $this->get('session')->get('comments');
        $sessionCountry = $this->get('session')->get('country');
        $sessionPaymentmethod = $this->get('session')->get('payment_method', 'credit_card');
        $sessionCartName = $this->get('session')->get('card_holder', '');
        $sessionCartNumber = $this->get('session')->get('card_number', '');
        $sessionCartYear = $this->get('session')->get('card_year', '');
        $sessionCartMonth = $this->get('session')->get('card_month', '');
        $sessionCartCvv = $this->get('session')->get('card_cvv', '');

// ????
//        if (empty($sessionEmail) || 0 === count($cartInfo['products'])) {
//            $redirect = $this->redirectToRoute('cart_page');
//            $redirect->headers->setCookie($cartService->getCartCookie());
//            $redirect->headers->setCookie($cartService->getCountryCookie());
//
//            return $redirect;
//        }

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        $response = $this->render($pageService->getConfiguration()->getName().'/checkout_success.html.twig', [
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'first_name' => $sessionFname,
            'last_name' => $sessionLname,
            'address1' => $sessionAddr1,
            'address2' => $sessionAddr2,
            'city' => $sessionCity,
            'state' => $sessionState,
            'country' => $sessionCountry,
            'zip' => $sessionZip,
            'page_name' => 'checkout_success_page',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->clearCookie(CartService::CART_COOKIE_NAME);
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }

    /**
     * @Route("/checkout-payment", name="checkout_payment_page")
     * @param PageService $pageService
     * @param CartService $cartService
     * @param Request $request
     * @param OrderService $orderService
     * @param RouterInterface $router
     * @return Response
     * @throws \InvalidArgumentException
     * @throws \Exception
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function checkoutPayment(PageService $pageService, CartService $cartService, Request $request, OrderService $orderService, RouterInterface $router, UserService $userService): Response
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));

        $cartService->setCookieCountry($this->get('session')->get('country'));
        $cartInfo = $cartService->getCart();

        $sessionEmail = $this->get('session')->get('email');
        $sessionFname = $this->get('session')->get('first_name');
        $sessionLname = $this->get('session')->get('last_name');
        $sessionAddr1 = $this->get('session')->get('address1');
        $sessionAddr2 = $this->get('session')->get('address2');
        $sessionCity = $this->get('session')->get('city');
        $sessionZip = $this->get('session')->get('zip');
        $sessionState = $this->get('session')->get('state');
        $sessionBillingasshipping = $this->get('session')->get('billing_as_shipping');
        $sessionComments = $this->get('session')->get('comments');
        $sessionCountry = $this->get('session')->get('country');

        $sessionBillingFname = $this->get('session')->get('billing_first_name', '');
        $sessionBillingLname = $this->get('session')->get('billing_last_name', '');
        $sessionBillingAddr1 = $this->get('session')->get('billing_address1', '');
        $sessionBillingAddr2 = $this->get('session')->get('billing_address2', '');
        $sessionBillingCity = $this->get('session')->get('billing_city', '');
        $sessionBillingZip = $this->get('session')->get('billing_zip', '');
        $sessionBillingState = $this->get('session')->get('billing_state', '');
        $sessionBillingCountry = $this->get('session')->get('billing_country', '');

        $sessionPaymentmethod = $this->get('session')->get('payment_method', 'credit_card');
        $sessionCartName = $this->get('session')->get('card_holder', '');
        $sessionCartNumber = $this->get('session')->get('card_number', '');
        $sessionCartYear = $this->get('session')->get('card_year', '');
        $sessionCartMonth = $this->get('session')->get('card_month', '');
        $sessionCartCvv = $this->get('session')->get('card_cvv', '');

        if (empty($sessionEmail) || 0 === count($cartInfo['products'])) {
            $redirect = $this->redirectToRoute('cart_page');
            $redirect->headers->setCookie($cartService->getCartCookie());
            $redirect->headers->setCookie($cartService->getCountryCookie());

            return $redirect;
        }

		// call afterpay configuration  - /v1/configuration endpoint
		$afterpayClient = new \GuzzleHttp\Client(['base_uri' => 'https://api-sandbox.afterpay.com']); 
		$apikey = base64_encode('40351'.':'.'5287f73ac23c6199705e0c9310537df1017a5a3e19361069dc2578ced0ab99524adaf94e34e55a64e4ff6c47742a3fcf0c4b4df5e624490a81be65d34cbf25fc');
		$response = $afterpayClient->request(Request::METHOD_GET, '/v1/configuration', [ 
						'headers' => [
        					'Authorization' => 'Basic '.$apikey,
        					'User-Agent' => 'AdultsmartAfterpayModule/1.0.0 (PHP/7.1.1; Merchant/40351)',
							'Accept' => 'application/json'  ] ]);
		// response for /v1/configuration 
		$afterpayResponse = json_decode((string)$response->getBody(), true); $enableAfterPay = 0; 
		//dump($afterpayResponse); dump($cartInfo['subtotal'] + $cartInfo['shipping']);
		if($afterpayResponse[0]['maximumAmount']['amount'] > 0){
  			if ( ( $cartInfo['subtotal'] + $cartInfo['shipping'] ) < floatval($afterpayResponse[0]['maximumAmount']['amount']) ){ 
				$enableAfterPay = 1; 
			}
		} //dump($enableAfterPay); exit;

        $checkoutPaymentForm = $this->createFormBuilder(null, ['allow_extra_fields' => true])
            ->add('action', HiddenType::class, ['data' => 'checkout_payment'])
//            ->add('paypal', RadioType::class, [
//                'data' => ''
//            ])
//            ->add('bank', RadioType::class)
//            ->add('check', RadioType::class)
//            ->add('payment', ChoiceType::class, [
//                'choices' => ['paypal', 'bank', 'check', 'credit_card'],
//                'expanded' => true,
//                'multiple' => false,
//                'required' => false,
//            ])
            ->setAction($this->generateUrl('checkout_payment_page', []))
            ->setMethod('POST')
            ->getForm();

        $errors = [];

        $checkoutPaymentForm->handleRequest($request);

        if ($checkoutPaymentForm->isSubmitted() && $checkoutPaymentForm->isValid()) {

        $captcha = $request->get('g-recaptcha-response');
        if ('' !== $captcha) {
            $client = new \GuzzleHttp\Client(['base_uri' => 'https://www.google.com/recaptcha/api/']);
            $response = $client->request(Request::METHOD_POST, 'siteverify', [
                    'form_params' => [
                        'secret' => '6LehDlQUAAAAAHqHrmz31Qnc5jXzeHmp_v8phNMN',
                        'response' => $captcha,
                        'remoteip' => $request->getClientIp(),
                ]
            ]);
            $response = json_decode((string)$response->getBody(), true); 
            if ($response['success'] !== true) {
                $errors['payment_method'][] = 'Robot verification failed, please try again.';
            }

            if(empty($errors)){

            $data = $checkoutPaymentForm->getData();
            $extraData = $checkoutPaymentForm->getExtraData();

            if ($data['action'] === 'checkout_payment' && $extraData['payment_method'] === 'credit_card') {
                // process credit card
                $this->get('session')->set('payment_method', $extraData['payment_method']);

                $this->get('session')->set('card_holder', $extraData['payment_creditcard']['card_holder']);
                $this->get('session')->set('card_number', $extraData['payment_creditcard']['card_number']);
                $this->get('session')->set('card_year', $extraData['payment_creditcard']['card_year']);
                $this->get('session')->set('card_month', $extraData['payment_creditcard']['card_month']);
                $this->get('session')->set('card_cvv', $extraData['payment_creditcard']['card_cvv']);
                $sessionCartName = $this->get('session')->get('card_holder', '');
                $sessionCartNumber = $this->get('session')->get('card_number', '');
                $sessionCartYear = $this->get('session')->get('card_year', '');
                $sessionCartMonth = $this->get('session')->get('card_month', '');
                $sessionCartCvv = $this->get('session')->get('card_cvv', '');

                $errors = $this->validateCard($extraData, $errors);

                if (empty($errors)) {
                    try {
                        $chargeResult = $orderService->chargeCc(
                            [
                                'email' => $sessionEmail,
                                'first_name' => $sessionFname,
                                'last_name' => $sessionLname,
                                'address1' => $sessionAddr1,
                                'address2' => $sessionAddr2,
                                'city' => $sessionCity,
                                'zip' => $sessionZip,
                                'state' => $sessionState,
                                'country' => $sessionCountry,
                            ],
                            $extraData['payment_creditcard'],
                            $cartInfo
                        );

//                        dump($chargeResult);
                    } catch (\Exception $e) {
                        $errors['payment_method'][] = $e->getMessage()/*.':'.$e->getLine()*/;
                    }

//                    dump($errors);

                    if (empty($errors) && true === $chargeResult) {
//                    if (true) {
                        // save order
                        $orderService->saveOrder(
                            [
                                'email' => $sessionEmail,
                                'first_name' => $sessionFname,
                                'last_name' => $sessionLname,
                                'address1' => $sessionAddr1,
                                'address2' => $sessionAddr2,
                                'city' => $sessionCity,
                                'zip' => $sessionZip,
                                'state' => $sessionState,
                                'country' => $sessionCountry,
                            ],
                            $cartInfo,
                            $extraData['payment_method'],
                            $pageService
                        );

                        // send email
                        $orderService->sendEmail(
                            [
                                'email' => $sessionEmail,
                                'first_name' => $sessionFname,
                                'last_name' => $sessionLname,
                                'address1' => $sessionAddr1,
                                'address2' => $sessionAddr2,
                                'city' => $sessionCity,
                                'zip' => $sessionZip,
                                'state' => $sessionState,
                                'country' => $sessionCountry,
                            ],
                            $cartInfo,
                            $extraData['payment_method'],
                            $pageService
                        );

                        $redirect = $this->redirectToRoute('checkout_success_page');
                        $redirect->headers->setCookie($cartService->getCountryCookie());
//                        $redirect->headers->clearCookie(CartService::CART_COOKIE_NAME);

                        return $redirect;
                    }
                }

            } elseif ($data['action'] === 'checkout_payment' && ($extraData['payment_method'] === 'bank' || $extraData['payment_method'] === 'check')) {
                // bank or check
                $this->get('session')->set('payment_method', $extraData['payment_method']);

                $orderService->saveOrder(
                    [
                        'email' => $sessionEmail,
                        'first_name' => $sessionFname,
                        'last_name' => $sessionLname,
                        'address1' => $sessionAddr1,
                        'address2' => $sessionAddr2,
                        'city' => $sessionCity,
                        'zip' => $sessionZip,
                        'state' => $sessionState,
                        'country' => $sessionCountry,
                    ],
                    $cartInfo,
                    $extraData['payment_method'],
                    $pageService,
                    false
                );

                // send email
                $orderService->sendEmail(
                    [
                        'email' => $sessionEmail,
                        'first_name' => $sessionFname,
                        'last_name' => $sessionLname,
                        'address1' => $sessionAddr1,
                        'address2' => $sessionAddr2,
                        'city' => $sessionCity,
                        'zip' => $sessionZip,
                        'state' => $sessionState,
                        'country' => $sessionCountry,
                    ],
                    $cartInfo,
                    $extraData['payment_method'],
                    $pageService
                );

                $redirect = $this->redirectToRoute('checkout_success_page');
                $redirect->headers->setCookie($cartService->getCountryCookie());
//                $redirect->headers->clearCookie(CartService::CART_COOKIE_NAME);

                return $redirect;

            } elseif ($data['action'] === 'checkout_payment' && $extraData['payment_method'] === 'paypal') {
                // bank or check
                $this->get('session')->set('payment_method', $extraData['payment_method']);

                $orderId = $orderService->saveOrder(
                    [
                        'email' => $sessionEmail,
                        'first_name' => $sessionFname,
                        'last_name' => $sessionLname,
                        'address1' => $sessionAddr1,
                        'address2' => $sessionAddr2,
                        'city' => $sessionCity,
                        'zip' => $sessionZip,
                        'state' => $sessionState,
                        'country' => $sessionCountry,
                    ],
                    $cartInfo,
                    $extraData['payment_method'],
                    $pageService,
                    false
                );

                $paypalRedirectUrl = $pageService->getPayPalRedirectUrl(
                    $router,
                    $cartInfo,
                    [
                        'email' => $sessionEmail,
                        'first_name' => $sessionFname,
                        'last_name' => $sessionLname,
                        'address1' => $sessionAddr1,
                        'address2' => $sessionAddr2,
                        'city' => $sessionCity,
                        'zip' => $sessionZip,
                        'state' => $sessionState,
                        'country' => $sessionCountry,
                    ],
                    [
                        'acct1.UserName' => $this->getParameter('paypal.username'),
                        'acct1.Password' => $this->getParameter('paypal.password'),
                        'acct1.Signature' => $this->getParameter('paypal.signature'),
                        'mode' => $this->getParameter('paypal.mode'),
                    ],
                    $orderId
                );

                // redirect to paypal
                return $this->redirect($paypalRedirectUrl);

            } elseif ($data['action'] === 'checkout_payment' && $extraData['payment_method'] === 'zippay') {
                // zippay
                $this->get('session')->set('payment_method', $extraData['payment_method']); 

                $orderId = $orderService->saveOrder(
                    [
                        'email' => $sessionEmail,
                        'first_name' => $sessionFname,
                        'last_name' => $sessionLname,
                        'address1' => $sessionAddr1,
                        'address2' => $sessionAddr2,
                        'city' => $sessionCity,
                        'zip' => $sessionZip,
                        'state' => $sessionState,
                        'country' => $sessionCountry,
                    ],
                    $cartInfo,
                    $extraData['payment_method'],
                    $pageService,
                    false
                ); //dump($orderId); exit;

                // send email
                $orderService->sendEmail(
                    [
                        'email' => $sessionEmail,
                        'first_name' => $sessionFname,
                        'last_name' => $sessionLname,
                        'address1' => $sessionAddr1,
                        'address2' => $sessionAddr2,
                        'city' => $sessionCity,
                        'zip' => $sessionZip,
                        'state' => $sessionState,
                        'country' => $sessionCountry,
                    ],
                    $cartInfo,
                    $extraData['payment_method'],
                    $pageService
                );

                $zippayRedirectUrl = $pageService->getZippayRedirectUrl(
                    $router,
                    $cartInfo,
                    [
                        'email' => $sessionEmail,
                        'first_name' => $sessionFname,
                        'last_name' => $sessionLname,
                        'address1' => $sessionAddr1,
                        'address2' => $sessionAddr2,
                        'city' => $sessionCity,
                        'zip' => $sessionZip,
                        'state' => $sessionState,
                        'country' => $sessionCountry,
                    ],
                    [
                        'acct1.UserName' => $this->getParameter('paypal.username'),
                        'acct1.Password' => $this->getParameter('paypal.password'),
                        'acct1.Signature' => $this->getParameter('paypal.signature'),
                        'mode' => $this->getParameter('paypal.mode'),
                    ],
                    $orderId
                );

                // redirect to Zippay
                return $this->redirect($zippayRedirectUrl);

            } elseif ($data['action'] === 'checkout_payment' && $extraData['payment_method'] === 'afterpay') {
                $this->get('session')->set('payment_method', $extraData['payment_method']); 
                $orderId = $orderService->saveOrder(
                    [
                        'email' => $sessionEmail,
                        'first_name' => $sessionFname,
                        'last_name' => $sessionLname,
                        'address1' => $sessionAddr1,
                        'address2' => $sessionAddr2,
                        'city' => $sessionCity,
                        'zip' => $sessionZip,
                        'state' => $sessionState,
                        'country' => $sessionCountry,
                    ],
                    $cartInfo,
                    $extraData['payment_method'],
                    $pageService,
                    false
                ); //dump($orderId); exit;

                // send email
                $orderService->sendEmail(
                    [
                        'email' => $sessionEmail,
                        'first_name' => $sessionFname,
                        'last_name' => $sessionLname,
                        'address1' => $sessionAddr1,
                        'address2' => $sessionAddr2,
                        'city' => $sessionCity,
                        'zip' => $sessionZip,
                        'state' => $sessionState,
                        'country' => $sessionCountry,
                    ],
                    $cartInfo,
                    $extraData['payment_method'],
                    $pageService
                );                

                $afterpayOrderToken = $pageService->getAfterPayOrderToken(
                    $router,
                    $cartInfo,
                    [
                        'email' => $sessionEmail,
                        'first_name' => $sessionFname,
                        'last_name' => $sessionLname,
                        'address1' => $sessionAddr1,
                        'address2' => $sessionAddr2,
                        'city' => $sessionCity,
                        'zip' => $sessionZip,
                        'state' => $sessionState,
                        'country' => $sessionCountry,
                    ],
                    [
                        'acct1.UserName' => $this->getParameter('paypal.username'),
                        'acct1.Password' => $this->getParameter('paypal.password'),
                        'acct1.Signature' => $this->getParameter('paypal.signature'),
                        'mode' => $this->getParameter('paypal.mode'),
                    ],
                    $orderId
                );

				$afterpayRedirectUrl = 'https://portal.sandbox.afterpay.com/au/checkout/?token='.$afterpayOrderToken;
                // redirect to Afterpay
                return $this->redirect($afterpayRedirectUrl);

            } else {
                // error
                $errors['payment_method'][] = 'Invalid payment method';
            }

            } //end if(empty($errors))

        } else {
            $errors['payment_method'][] = 'Please click on the reCAPTCHA box.';
        }

        }

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        $response = $this->render($pageService->getConfiguration()->getName().'/checkout_payment.html.twig', [
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'first_name' => $sessionFname,
            'last_name' => $sessionLname,
            'address1' => $sessionAddr1,
            'address2' => $sessionAddr2,
            'city' => $sessionCity,
            'state' => $sessionState,
            'country' => $sessionCountry,
            'zip' => $sessionZip,
            'card_holder' => $sessionCartName,
            'card_number' => $sessionCartNumber,
            'card_year' => $sessionCartYear,
            'card_month' => $sessionCartMonth,
            'card_cvv' => $sessionCartCvv,
            'payment_method' => $sessionPaymentmethod,
            'form' => $checkoutPaymentForm->createView(),
            'errors' => $errors,
            'page_name' => 'checkout_payment_page',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header, 
			'enableAfterPay' => $enableAfterPay,
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }

    /**
     * @Route("/checkout-shipping", name="checkout_shipping_page")
     * @param PageService $pageService
     * @param CartService $cartService
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function checkoutShipping(PageService $pageService, CartService $cartService, Request $request, UserService $userService): Response
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));
        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($cookieCountry);

        $cartInfo = $cartService->getCart();
        if (0 === count($cartInfo['products'])) {
            $redirect = $this->redirectToRoute('cart_page');
            $redirect->headers->setCookie($cartService->getCartCookie());
            $redirect->headers->setCookie($cartService->getCountryCookie());

            return $redirect;
        }

		if($userService->getUser() == null){
          $sessionEmail = $this->get('session')->get('email', '');
          $sessionFname = $this->get('session')->get('first_name', '');
          $sessionLname = $this->get('session')->get('last_name', '');
          $sessionAddr1 = $this->get('session')->get('address1', '');
          $sessionAddr2 = $this->get('session')->get('address2', '');
          $sessionCity = $this->get('session')->get('city', '');
          $sessionZip = $this->get('session')->get('zip', '');
          $sessionState = $this->get('session')->get('state', '');
          $sessionBillingasshipping = $this->get('session')->get('billing_as_shipping', false);
          $sessionComments = $this->get('session')->get('comments', '');
          $sessionCountry = $this->get('session')->get('country', $cookieCountry);
          $sessionBillingFname = $this->get('session')->get('billing_first_name', '');
          $sessionBillingLname = $this->get('session')->get('billing_last_name', '');
          $sessionBillingAddr1 = $this->get('session')->get('billing_address1', '');
          $sessionBillingAddr2 = $this->get('session')->get('billing_address2', '');
          $sessionBillingCity = $this->get('session')->get('billing_city', '');
          $sessionBillingZip = $this->get('session')->get('billing_zip', '');
          $sessionBillingState = $this->get('session')->get('billing_state', '');
          $sessionBillingCountry = $this->get('session')->get('billing_country', $cookieCountry);
	 	}else{
			$list_orders = $pageService->getOrders($userService->getUser()->getCustomersId()); 
			$name = explode(' ', $list_orders[0]->getCustomersName()); $size = count($name); 
			$cnt=0; $firstname=''; $lastname=''; 
			foreach($name as $value){ 
			  if($cnt == ($size-1)){ $lastname = $name[$cnt]; $cnt++; }else{ $firstname .= $name[$cnt]; $cnt++; }
			}
			$caddress = explode(' ', $list_orders[0]->getCustomersStreetAddress()); $size = count($caddress); 
			$cnt=0; $streetAddress1=''; $streetAddress2='';
			foreach($caddress as $value){ 
			  if($cnt == ($size-1)){ $streetAddress2 = $caddress[$cnt]; $cnt++; }else{ $streetAddress1 .= $caddress[$cnt]; $cnt++; }
			}

			$bname = explode(' ', $list_orders[0]->getBillingName()); $size = count($bname); 
			$cnt=0; $bfirstname=''; $blastname=''; 
			foreach($name as $value){ 
			  if($cnt == ($size-1)){ $blastname = $name[$cnt]; $cnt++; }else{ $bfirstname .= $name[$cnt]; $cnt++; }
			}
			$bcaddress = explode(' ', $list_orders[0]->getCustomersStreetAddress()); $size = count($bcaddress); 
			$cnt=0; $bstreetAddress1=''; $bstreetAddress2='';
			foreach($bcaddress as $value){ 
			  if($cnt == ($size-1)){ $bstreetAddress2 = $bcaddress[$cnt]; $cnt++; }else{ $bstreetAddress1 .= $bcaddress[$cnt]; $cnt++; }
			}
			//set the values in sessions
			$this->get('session')->set('email', $list_orders[0]->getCustomersEmailAddress());
            $this->get('session')->set('first_name', $firstname);
            $this->get('session')->set('last_name', $lastname);
            $this->get('session')->set('address1', $streetAddress1);
            $this->get('session')->set('address2', $streetAddress2);
            $this->get('session')->set('city', $list_orders[0]->getCustomersCity());
            $this->get('session')->set('zip', $list_orders[0]->getCustomersPostcode());
            $this->get('session')->set('state', $list_orders[0]->getCustomersState());
            $this->get('session')->set('billing_as_shipping', false);
            $this->get('session')->set('comments', '');
            $this->get('session')->set('country', $cookieCountry);
			//set billing address in session
            $this->get('session')->set('billing_first_name', $bfirstname);
            $this->get('session')->set('billing_last_name', $blastname);
            $this->get('session')->set('billing_address1', $bstreetAddress1);
            $this->get('session')->set('billing_address2', $bstreetAddress2);
            $this->get('session')->set('billing_city', $list_orders[0]->getBillingCity());
            $this->get('session')->set('billing_zip', $list_orders[0]->getBillingPostcode());
            $this->get('session')->set('billing_state', $list_orders[0]->getBillingState());
            $this->get('session')->set('billing_country', $cookieCountry);
			/** get respective values from session */
			$sessionEmail = $this->get('session')->get('email');
            $sessionFname = $this->get('session')->get('first_name');
            $sessionLname = $this->get('session')->get('last_name');
            $sessionAddr1 = $this->get('session')->get('address1');
            $sessionAddr2 = $this->get('session')->get('address2');
            $sessionCity = $this->get('session')->get('city');
            $sessionZip = $this->get('session')->get('zip');
            $sessionState = $this->get('session')->get('state');
            $sessionBillingasshipping = $this->get('session')->get('billing_as_shipping', false);
            $sessionComments = $this->get('session')->get('comments', '');
            $sessionCountry = $this->get('session')->get('country');
            $sessionBillingFname = $this->get('session')->get('billing_first_name');
            $sessionBillingLname = $this->get('session')->get('billing_last_name');
            $sessionBillingAddr1 = $this->get('session')->get('billing_address1');
            $sessionBillingAddr2 = $this->get('session')->get('billing_address2');
            $sessionBillingCity = $this->get('session')->get('billing_city');
            $sessionBillingZip = $this->get('session')->get('billing_zip');
            $sessionBillingState = $this->get('session')->get('billing_state');
            $sessionBillingCountry = $this->get('session')->get('billing_country');
		}

        $countries = $pageService->getCountries();
        $choices = []; $statusText = '';
        foreach ($countries as $country) {
            $choices[$country->getCountriesName()] = $country->getCountriesIsoCode2();
        }

        $checkoutShippingForm = $this->createFormBuilder(null, ['allow_extra_fields' => true])
            ->add('action', HiddenType::class, ['data' => 'checkout_shipping'])
            ->add(
                'country',
                ChoiceType::class,
                [
                    'choices' => $choices,
                    'preferred_choices' => [$sessionCountry]
                ]
            )
            ->add('email', EmailType::class, ['data' => $sessionEmail])
            ->add('first_name', TextType::class, ['data' => $sessionFname])
            ->add('last_name', TextType::class, ['data' => $sessionLname])
            ->add('address1', TextType::class, ['data' => $sessionAddr1])
            ->add('address2', TextType::class, ['data' => $sessionAddr2, 'required' => false])
            ->add('city', TextType::class, ['data' => $sessionCity])
            ->add('zip', TextType::class, ['data' => $sessionZip])
            ->add('state', TextType::class, ['data' => $sessionState])
            ->add(
                'billing_country',
                ChoiceType::class,
                [
                    'choices' => $choices,
                    'preferred_choices' => [$sessionBillingCountry],
                    'required' => false,
                ]
            )
            ->add('billing_first_name', TextType::class, ['data' => $sessionBillingFname, 'required' => false])
            ->add('billing_last_name', TextType::class, ['data' => $sessionBillingLname, 'required' => false])
            ->add('billing_address1', TextType::class, ['data' => $sessionBillingAddr1, 'required' => false])
            ->add('billing_address2', TextType::class, ['data' => $sessionBillingAddr2, 'required' => false])
            ->add('billing_city', TextType::class, ['data' => $sessionBillingCity, 'required' => false])
            ->add('billing_zip', TextType::class, ['data' => $sessionBillingZip, 'required' => false])
            ->add('billing_state', TextType::class, ['data' => $sessionBillingState, 'required' => false])
            ->add('billing_as_shipping', CheckboxType::class, [
                'data' => $sessionBillingasshipping,
                'required' => false
            ])
            ->add('comments', TextareaType::class, ['data' => $sessionComments, 'required' => false])
            ->setAction($this->generateUrl('checkout_shipping_page', []))
            ->setMethod('POST')
            ->getForm();

        $checkoutShippingForm->handleRequest($request);

        if ($checkoutShippingForm->isSubmitted() && $checkoutShippingForm->isValid()) { 

        $captcha = $request->get('g-recaptcha-response');
        if ('' !== $captcha) {
           $client = new \GuzzleHttp\Client(['base_uri' => 'https://www.google.com/recaptcha/api/']);
           $response = $client->request(Request::METHOD_POST, 'siteverify', [
               'form_params' => [
                    'secret' => '6LehDlQUAAAAAHqHrmz31Qnc5jXzeHmp_v8phNMN',
                    'response' => $captcha,
                    'remoteip' => $request->getClientIp(),
               ]
          ]);
          $response = json_decode((string)$response->getBody(), true);

          if ($response['success'] === true) {
            $data = $checkoutShippingForm->getData();
            $this->get('session')->set('email', $data['email']);
            $this->get('session')->set('first_name', $data['first_name']);
            $this->get('session')->set('last_name', $data['last_name']);
            $this->get('session')->set('address1', $data['address1']);
            $this->get('session')->set('address2', $data['address2']);
            $this->get('session')->set('city', $data['city']);
            $this->get('session')->set('zip', $data['zip']);
            $this->get('session')->set('state', $data['state']);
            $this->get('session')->set('billing_as_shipping', $data['billing_as_shipping']);
            $this->get('session')->set('comments', $data['comments']);
            $this->get('session')->set('country', $data['country']);
            $cartService->setCookieCountry($data['country']);

            $this->get('session')->set('billing_first_name', $data['billing_first_name']);
            $this->get('session')->set('billing_last_name', $data['billing_last_name']);
            $this->get('session')->set('billing_address1', $data['billing_address1']);
            $this->get('session')->set('billing_address2', $data['billing_address2']);
            $this->get('session')->set('billing_city', $data['billing_city']);
            $this->get('session')->set('billing_zip', $data['billing_zip']);
            $this->get('session')->set('billing_state', $data['billing_state']);
            $this->get('session')->set('billing_country', $data['billing_country']);

            $redirect = $this->redirectToRoute('checkout_payment_page');
            $redirect->headers->setCookie($cartService->getCartCookie());
            $redirect->headers->setCookie($cartService->getCountryCookie());

            return $redirect;
          }

        } else {
	  $statusText = 'Error: Please click On Captcha to proceed';   
        }  
	}

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        $response = $this->render($pageService->getConfiguration()->getName().'/checkout_shipping.html.twig', [
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'form' => $checkoutShippingForm->createView(),
            'page_name' => 'checkout_shipping_page',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'user' => $userService->getUser(),
	    'statusText' => $statusText,
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }

    /**
     * @Route("/brand/{brandSlug}_{brandId}", name="brand_page")
     * @param string $brandSlug
     * @param int $brandId
     * @param PageService $pageService
     * @param CartService $cartService
     * @param Request $request
     * @param UserService $userService
     * @return Response
     * @throws \Exception
     */
    public function brand(
        string $brandSlug,
        int $brandId,
        PageService $pageService,
        CartService $cartService,
        Request $request,
        UserService $userService
    ): Response
    {
        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));

        $cartInfo = $cartService->getCart();

        $page = $request->get('page', 1) - 1;
        $start = $page * $pageService->getConfiguration()->getNumProductsPerPage();
        $query = $request->get('q', '');

        $searchInfo = $pageService->brand($brandId, $start, $pageService->getConfiguration()->getNumProductsPerPage());

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        $response = $this->render($pageService->getConfiguration()->getName().'/brand.html.twig', [
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'page_name' => 'brand',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'products' => $searchInfo['results'],
            'count' => $searchInfo['count'],
            'brand' => $searchInfo['brand'],
            'brandSlug' => $brandSlug,
            'brandId' => $brandId,
            'pages' => ceil($searchInfo['count'] / $pageService->getConfiguration()->getNumProductsPerPage()),
            'page' => $page + 1,
            'query' => $query,
            'visible_pages' => $pageService->getVisiblePages($page + 1, ceil($searchInfo['count'] / $pageService->getConfiguration()->getNumProductsPerPage())),
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }

    /**
     * @Route("/{categorySlug}/{productSlug}-p{productId}.html", name="product_page_by_slug", requirements={"productId"="\d+", "categorySlug"=".+", "productSlug"=".+"})
     * @param string $categorySlug
     * @param string $productSlug
     * @param int $productId
     * @param PageService $pageService
     * @param CartService $cartService
     * @param Request $request
     * @param UserService $userService
     * @return Response
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @throws \Exception
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function productBySlug(
        string $categorySlug,
        string $productSlug,
        int $productId,
        PageService $pageService,
        CartService $cartService,
        Request $request,
        UserService $userService
    ): Response
    {
        
        $mainCategory = explode('/', $categorySlug);
        $category = $mainCategory[0];

        try {
            $productInfo = $pageService->product($productId, $category);
        } catch (NoResultException $e) {
            throw $this->createNotFoundException($e->getMessage(), $e);
        }

        $addToCartForm = $this->createFormBuilder(null, ['allow_extra_fields' => true])
            ->setAction($this->generateUrl('product_page_by_slug', ['categorySlug' => $categorySlug, 'productId' => $productId, 'productSlug' => $productSlug]))
            ->setMethod('POST')
            ->getForm();

        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));
        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($cookieCountry);

		//generate wishlist
		if( $userService->getUser() != null ){ $wishlist = $userService->getUser()->getCustomersNotes(); }
		else { $wishlist = array(); }

        $addToCartForm->handleRequest($request);

        /*if ($addToCartForm->isSubmitted() && $addToCartForm->isValid()) {
            $data = $addToCartForm->getExtraData();
            $cartService->add($productId, $data['Quantity'], $data['ProductOption'] ?? []);
            foreach ($data['AlsoPurchased'] ?? [] as $alsoPurchasedProductId) {
                $cartService->add($alsoPurchasedProductId, 1, $alsoPurchasedProductId['ProductOption'] ?? []);
            }

            $redirect = $this->redirectToRoute('cart_page');
            $redirect->headers->setCookie($cartService->getCartCookie());
            $redirect->headers->setCookie($cartService->getCountryCookie());

            return $redirect;
        }*/

		if($request->request->get('addtocart') !== null){
        if ($addToCartForm->isSubmitted() && $addToCartForm->isValid()) { 
            $data = $addToCartForm->getExtraData();
            $cartService->add($productId, $data['Quantity'], $data['ProductOption'] ?? []);

            foreach ($data['AlsoPurchased'] ?? [] as $alsoPurchasedProductId) {
                $cartService->add($alsoPurchasedProductId, 1, $alsoPurchasedProductId['ProductOption'] ?? []);
            }

            $redirect = $this->redirectToRoute('cart_page');
            $redirect->headers->setCookie($cartService->getCartCookie());
            $redirect->headers->setCookie($cartService->getCountryCookie());

            return $redirect;
        }}

		if($request->request->get('addtowishlist') !== null){
        if ($addToCartForm->isSubmitted() ){ //&& $addToCartForm->isValid()) { 
			//check if user is logged in, else display error msg
			if (null === $userService->getUser()) { 
				$this->addFlash('error','Error: Please Login To Add To Wishlist');
 
				$redirect = $this->redirectToRoute('product_page_by_slug', ['categorySlug' => $categorySlug, 'productId' => $productId, 'productSlug' => $productSlug] );
            	$redirect->headers->setCookie($cartService->getCartCookie());
            	$redirect->headers->setCookie($cartService->getCountryCookie());

			} else {
                if(!in_array($request->attributes->get('productId'), $wishlist)){
				  array_push($wishlist, $request->attributes->get('productId'));  
				  if( $userService->setCustomerWishlist($wishlist) ){
				  	$this->addFlash('success','Success: Product Has Been Added To Wishlist');
				  } else { $this->addFlash('error','Error: Product Could Not Be Added To Wishlist'); }
				} else {
				  $this->addFlash('info','Info: Product Already In Wishlist');
				} 

				$redirect = $this->redirectToRoute('product_page_by_slug', ['categorySlug' => $categorySlug, 'productId' => $productId, 'productSlug' => $productSlug] );
            	$redirect->headers->setCookie($cartService->getCartCookie());
            	$redirect->headers->setCookie($cartService->getCountryCookie());
			}
            return $redirect;
        }}

        $cartInfo = $cartService->getCart();

        $metaTitle = '';
        $metaDescription = '';
        $metaKeywords = '';
        $header = '';

        $response = $this->render($pageService->getConfiguration()->getName().'/product.html.twig', [
            'product' => $productInfo['product'],
            'form' => $addToCartForm->createView(),
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'page_name' => 'product_page',
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'page_service' => $pageService,
            'product_slug' => $productSlug,
            'category_slug' => $categorySlug,
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }

    /**
     * @Route("/{categorySlug}-c{categoryId}.html", name="category_page_by_slug", requirements={"categoryId"="\d+", "categorySlug"=".+"})
     * @param string $categorySlug
     * @param int $categoryId
     * @param PageService $pageService
     * @param Request $request
     * @param CartService $cartService
     * @return Response
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function categoryBySlug(string $categorySlug, int $categoryId, PageService $pageService, Request $request, CartService $cartService, UserService $userService): Response
    {
        $mainCategory = explode('/', $categorySlug);
        $category = $mainCategory[0];

        if ($category === 'uncut-dvds') {
            throw $this->createNotFoundException('Not found', new \Exception('Tried to access uncut dvds cat'));
        }

        $categoryTemplate = $pageService->getCategoryTemplate($categorySlug);

        $cookieCountry = $request->cookies->get(CartService::CART_COUNTRY_COOKIE_NAME, 'AU');
        $cartService->setCookieCountry($cookieCountry);

        $page = $request->get('page', 1) - 1;
        $start = $page * $pageService->getConfiguration()->getNumProductsPerPage();

        $sortByForm = $this->getSortByForm();
        $sortByForm->handleRequest($request);
        $sortBy = $request->get('sort', 'newest');
        if ($sortByForm->isSubmitted() && $sortByForm->isValid()) {
            $data = $sortByForm->getData();
            if ($data['sort']) {
                $sortBy = $data['sort'];
            }
        }

        $brandsForm = $this->getBrandsForm($categoryId, $pageService);
        $brandsForm->handleRequest($request);
        $brand = $request->get('brand', 0);
        if ($brandsForm->isSubmitted() && $brandsForm->isValid()) {
            $data = $brandsForm->getData();
            if ((int)$data['brand'] !== 0) {
                $brand = (int)$data['brand'];
            }
        }

        $priceForm = $this->getPricesForm();
        $priceForm->handleRequest($request);
        $price = $request->get('price', 'all');
        if ($priceForm->isSubmitted() && $priceForm->isValid()) {
            $data = $priceForm->getData();
            if ($data['price'] === '0-20') {
                $price = '0-20';
            } elseif ($data['price'] === '20-50') {
                $price = '20-50';
            } elseif ($data['price'] === '50-100') {
                $price = '50-100';
            } elseif ($data['price'] === '100-200') {
                $price = '100-200';
            }
        }

        $categoryInfo = $pageService->categoryById($categoryId, $brand, $price, $sortBy, $start, $pageService->getConfiguration()->getNumProductsPerPage());

        $cartService->setCookie($request->cookies->get(CartService::CART_COOKIE_NAME, ''));
        $userService->setCookie($request->cookies->get(UserService::USER_COOKIE_NAME, ''));

        $cartInfo = $cartService->getCart();

        if ($category == '') {
            $metaTitle = '';
            $metaDescription = '';
            $metaKeywords = '';
            $header = '';
        } elseif ($category == '') {
            $metaTitle = '';
            $metaDescription = '';
            $metaKeywords = '';
            $header = '';
        } else {
            $metaTitle = '';
            $metaDescription = '';
            $metaKeywords = '';
            $header = '';
        }

        $response = $this->render(
            $pageService->getConfiguration()->getName().'/'.$categoryTemplate.'.html.twig', [
            'products' => $categoryInfo['results'],
            'count' => $categoryInfo['count'],
            'category_info' => $categoryInfo['category'],
            'page_service' => $pageService,
            'pages' => ceil($categoryInfo['count'] / $pageService->getConfiguration()->getNumProductsPerPage()),
            'page' => $page + 1,
            'category' => $pageService->generateCategorySlug($categoryInfo['category']),
            'visible_pages' => $pageService->getVisiblePages($page + 1, ceil($categoryInfo['count'] / $pageService->getConfiguration()->getNumProductsPerPage())),
            'cart_products' => $cartInfo['products'],
            'cart_subtotal' => $cartInfo['subtotal'],
            'cart_shipping' => $cartInfo['shipping'],
            'page_name' => $category,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'header' => $header,
            'sort_form' => $sortByForm->createView(),
            'price_form' => $priceForm->createView(),
            'brand_form' => $brandsForm->createView(),
            'sort_by' => $sortBy,
            'price' => $price,
            'brand' => $brand,
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }

    /**
     * @Route("/load-totals")
     * @param Request $request
     * @param PageService $pageService
     * @return JsonResponse
     */
    public function totals(Request $request, PageService $pageService): JsonResponse
    {
        return $this->json(['subtotal' => 0, 'shipping' => 0, 'total' => 0]);
    }

    /**
     * @param $choices
     * @param $cookieCountry
     * @return FormInterface
     */
    private function getUpdateCountryForm($choices, $cookieCountry): FormInterface
    {
        $updateCountryForm = $this->createFormBuilder(null, ['allow_extra_fields' => true])
            ->add('action', HiddenType::class, ['data' => 'update_country'])
            ->add(
                'country',
                ChoiceType::class,
                [
                    'choices' => $choices,
                    'preferred_choices' => [$cookieCountry]
                ]
            )
            ->setAction($this->generateUrl('update_country', []))
            ->setMethod('POST')
            ->getForm();

        return $updateCountryForm;
    }

    /**
     * @return FormInterface
     */
    private function getUpdateQuantityForm(): FormInterface
    {
        $updateForm = $this->createFormBuilder(null, ['allow_extra_fields' => true])
            ->add('idx', HiddenType::class)
            ->add('action', HiddenType::class, ['data' => 'update'])
            ->add('quantity', IntegerType::class)
            ->setAction($this->generateUrl('update_quantity', []))
            ->setMethod('POST')
            ->getForm();

        return $updateForm;
    }

    /**
     * @return FormInterface
     */
    private function getRemoveProductForm(): FormInterface
    {
        $removeForm = $this->createFormBuilder(null, ['allow_extra_fields' => true])
            ->add('idx', HiddenType::class)
            ->add('action', HiddenType::class, ['data' => 'remove'])
            ->setAction($this->generateUrl('remove_product', []))
            ->setMethod('POST')
            ->getForm();

        return $removeForm;
    }

    /**
     * @return FormInterface
     */
    private function getSortByForm(): FormInterface
    {
        $sortByForm = $this->createFormBuilder(null, ['allow_extra_fields' => true, 'csrf_protection' => false])
            ->add(
                'sort',
                ChoiceType::class,
                [
                    'choices' => ['Newest' => 'newest', 'Price' => 'price', 'Bestsellers' => 'bestseller', 'A-Z' => 'alpha'],
                    'preferred_choices' => ['newest']
                ]
            )
            ->setMethod('GET')
            ->getForm();

        return $sortByForm;
    }

    /**
     * @param $extraData
     * @param $errors
     * @return mixed
     * @throws \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    private function validateCard($extraData, $errors)
    {
        $validator = Validation::createValidator();

        $violations = $validator->validate(
            $extraData['payment_creditcard']['card_holder'],
            [
                new Length(['min' => 3]),
                new NotBlank(),
            ]
        );
        if (0 !== count($violations)) {
            foreach ($violations as $violation) {
                $errors['payment_creditcard']['card_holder'][] = $violation->getMessage();
            }
        }

        $violations = $validator->validate(
            $extraData['payment_creditcard']['card_number'],
            [
                new NotBlank(),
                new CardScheme(['schemes' => ['MASTERCARD', 'VISA', 'AMEX']])
            ]
        );
        if (0 !== count($violations)) {
            foreach ($violations as $violation) {
                $errors['payment_creditcard']['card_number'][] = $violation->getMessage();
            }
        }

        $violations = $validator->validate(
            $extraData['payment_creditcard']['card_year'],
            [
                new NotBlank(),
                new Range(['min' => date('Y'), 'max' => date('Y') + 20])
            ]
        );
        if (0 !== count($violations)) {
            foreach ($violations as $violation) {
                $errors['payment_creditcard']['card_year'][] = $violation->getMessage();
            }
        }

        $violations = $validator->validate(
            $extraData['payment_creditcard']['card_month'],
            [
                new NotBlank(),
                new Range(['min' => 1, 'max' => 12])
            ]
        );
        if (0 !== count($violations)) {
            foreach ($violations as $violation) {
                $errors['payment_creditcard']['card_month'][] = $violation->getMessage();
            }
        }

        $violations = $validator->validate(
            $extraData['payment_creditcard']['card_cvv'],
            [
                new NotBlank(),
            ]
        );
        if (0 !== count($violations)) {
            foreach ($violations as $violation) {
                $errors['payment_creditcard']['card_cvv'][] = $violation->getMessage();
            }
        }

        return $errors;
    }

    /**
     * @param int $categoryId
     * @param PageService $pageService
     * @return FormInterface
     * @throws \Exception
     */
    private function getBrandsForm(int $categoryId, PageService $pageService): FormInterface
    {
        $choices = ['All' => 0];
        /** @var Manufacturer $brand */
        foreach ($pageService->brands($categoryId) as $brand) {
            $choices[$brand->getManufacturersName()] = $brand->getManufacturersId();
        }

        $brandsForm = $this->createFormBuilder(null, ['allow_extra_fields' => true, 'csrf_protection' => false])
            ->add(
                'brand',
                ChoiceType::class,
                [
                    'choices' => $choices,
                    'preferred_choices' => [0]
                ]
            )
            ->setMethod('GET')
            ->getForm();

        return $brandsForm;
    }

    /**
     * @return FormInterface
     */
    private function getPricesForm(): FormInterface
    {
        $choices = [
            'Under $20' => '0-20',
            '$20 - $50' => '20-50',
            '$50 - $100' => '50-100',
            '$100 - $200' => '100-200',
            'All Prices' => 'all',
        ];

        $pricesForm = $this->createFormBuilder(null, ['allow_extra_fields' => true, 'csrf_protection' => false])
            ->add(
                'price',
                ChoiceType::class,
                [
                    'choices' => $choices,
                    'preferred_choices' => ['all']
                ]
            )
            ->setMethod('GET')
            ->getForm();

        return $pricesForm;
    }

    /**
     * @return FormInterface
     */
    private function getLoginForm(): FormInterface
    {
        $form = $this->createFormBuilder(null, ['allow_extra_fields' => true])
            ->add('email', EmailType::class)
            ->add('password', PasswordType::class)
            ->setAction($this->generateUrl('do_login_page', []))
            ->setMethod('POST')
            ->getForm();

        return $form;
    }

    /**
     * @return FormInterface
     */
    private function getRegisterForm(): FormInterface
    {
        $form = $this->createFormBuilder(null, ['allow_extra_fields' => true])
            ->add('email', EmailType::class)
            ->add('first_name', TextType::class)
            ->add('last_name', TextType::class)
            ->add('dob', TextType::class)
            ->add('password', PasswordType::class)
            ->setAction($this->generateUrl('do_register_page', []))
            ->setMethod('POST')
            ->getForm();

        return $form;
    }

    private function getForgottenPasswordForm(): FormInterface
    {
        $form = $this->createFormBuilder(null, ['allow_extra_fields' => true])
            ->add('email', EmailType::class)
            ->setAction($this->generateUrl('do_forgot_password_page', []))
            ->setMethod('POST')
            ->getForm();

        return $form;
    }
}
