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
           			->add('email', EmailType::class, array('data' => $user->getCustomersEmailAddress(), 'required' => true ))
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

			if($result){ $this->addFlash('success','Password Updated'); } else { $this->addFlash('error','Password Could Not Be Updated'); }

            $redirect = $this->redirectToRoute('my_account_page');
            $redirect->headers->setCookie($cartService->getCartCookie());
            $redirect->headers->setCookie($cartService->getCountryCookie());
            $redirect->headers->setCookie($userService->getUserCookie());

            return $redirect;
        }}

        $cartInfo = $cartService->getCart();
        $orders = $pageService->getOrders($userService->getUser()->getCustomersId());

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
            'user' => $userService->getUser(),
        ]);
        $response->headers->setCookie($cartService->getCartCookie());
        $response->headers->setCookie($cartService->getCountryCookie());
        $response->headers->setCookie($userService->getUserCookie());

        return $response;
    }
