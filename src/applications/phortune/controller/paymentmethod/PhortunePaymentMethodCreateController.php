<?php

final class PhortunePaymentMethodCreateController
  extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $account_id = $request->getURIData('accountID');
    $account = id(new PhortuneAccountQuery())
      ->setViewer($viewer)
      ->withIDs(array($account_id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$account) {
      return new Aphront404Response();
    }

    $cart_id = $request->getInt('cartID');
    $subscription_id = $request->getInt('subscriptionID');
    $merchant_id = $request->getInt('merchantID');

    if ($cart_id) {
      $cart = id(new PhortuneCartQuery())
        ->setViewer($viewer)
        ->withAccountPHIDs(array($account->getPHID()))
        ->withIDs(array($cart_id))
        ->executeOne();
      if (!$cart) {
        return new Aphront404Response();
      }

      $subscription_phid = $cart->getSubscriptionPHID();
      if ($subscription_phid) {
        $subscription = id(new PhortuneSubscriptionQuery())
          ->setViewer($viewer)
          ->withAccountPHIDs(array($account->getPHID()))
          ->withPHIDs(array($subscription_phid))
          ->executeOne();
        if (!$subscription) {
          return new Aphront404Response();
        }
      } else {
        $subscription = null;
      }

      $merchant = $cart->getMerchant();

      $cart_id = $cart->getID();
      $subscription_id = null;
      $merchant_id = null;

      $next_uri = $cart->getCheckoutURI();
    } else if ($subscription_id) {
      $subscription = id(new PhortuneSubscriptionQuery())
        ->setViewer($viewer)
        ->withAccountPHIDs(array($account->getPHID()))
        ->withIDs(array($subscription_id))
        ->executeOne();
      if (!$subscription) {
        return new Aphront404Response();
      }

      $cart = null;
      $merchant = $subscription->getMerchant();

      $cart_id = null;
      $subscription_id = $subscription->getID();
      $merchant_id = null;

      $next_uri = $subscription->getURI();
    } else if ($merchant_id) {
      $merchant_phids = $account->getMerchantPHIDs();
      if ($merchant_phids) {
        $merchant = id(new PhortuneMerchantQuery())
          ->setViewer($viewer)
          ->withIDs(array($merchant_id))
          ->withPHIDs($merchant_phids)
          ->executeOne();
      } else {
        $merchant = null;
      }

      if (!$merchant) {
        return new Aphront404Response();
      }

      $cart = null;
      $subscription = null;

      $cart_id = null;
      $subscription_id = null;
      $merchant_id = $merchant->getID();

      $next_uri = $account->getPaymentMethodsURI();
    } else {
      $next_uri = $account->getPaymentMethodsURI();

      $merchant_phids = $account->getMerchantPHIDs();
      if ($merchant_phids) {
        $merchants = id(new PhortuneMerchantQuery())
          ->setViewer($viewer)
          ->withPHIDs($merchant_phids)
          ->needProfileImage(true)
          ->execute();
      } else {
        $merchants = array();
      }

      if (!$merchants) {
        return $this->newDialog()
          ->setTitle(pht('No Merchants'))
          ->appendParagraph(
            pht(
              'You have not established a relationship with any merchants '.
              'yet. Create an order or subscription before adding payment '.
              'methods.'))
          ->addCancelButton($next_uri);
      }

      // If there's more than one merchant, ask the user to pick which one they
      // want to pay. If there's only one, just pick it for them.
      if (count($merchants) > 1) {
        $menu = $this->newMerchantMenu($merchants);

        $form = id(new AphrontFormView())
          ->appendInstructions(
            pht(
              'Choose the merchant you want to pay.'));

        return $this->newDialog()
          ->setTitle(pht('Choose a Merchant'))
          ->appendForm($form)
          ->appendChild($menu)
          ->addCancelButton($next_uri);
      }

      $cart = null;
      $subscription = null;
      $merchant = head($merchants);

      $cart_id = null;
      $subscription_id = null;
      $merchant_id = $merchant->getID();
    }

    $providers = $this->loadCreatePaymentMethodProvidersForMerchant($merchant);
    if (!$providers) {
      throw new Exception(
        pht(
          'There are no payment providers enabled that can add payment '.
          'methods.'));
    }

    $state_params = array(
      'cartID' => $cart_id,
      'subscriptionID' => $subscription_id,
      'merchantID' => $merchant_id,
    );
    $state_params = array_filter($state_params);

    $state_uri = new PhutilURI($request->getRequestURI());
    foreach ($state_params as $key => $value) {
      $state_uri->replaceQueryParam($key, $value);
    }

    $provider_id = $request->getInt('providerID');
    if (isset($providers[$provider_id])) {
      $provider = $providers[$provider_id];
    } else {
      // If there's more than one provider, ask the user to pick how they
      // want to pay. If there's only one, just pick it.
      if (count($providers) > 1) {
        $menu = $this->newProviderMenu($providers, $state_uri);

        return $this->newDialog()
          ->setTitle(pht('Choose a Payment Method'))
          ->appendChild($menu)
          ->addCancelButton($next_uri);
      }

      $provider = head($providers);
    }

    $provider_id = $provider->getProviderConfig()->getID();

    $state_params['providerID'] = $provider_id;

    $errors = array();
    $display_exception = null;
    if ($request->isFormPost() && $request->getBool('isProviderForm')) {
      $method = id(new PhortunePaymentMethod())
        ->setAccountPHID($account->getPHID())
        ->setAuthorPHID($viewer->getPHID())
        ->setMerchantPHID($merchant->getPHID())
        ->setProviderPHID($provider->getProviderConfig()->getPHID())
        ->setStatus(PhortunePaymentMethod::STATUS_ACTIVE);

      // Limit the rate at which you can attempt to add payment methods. This
      // is intended as a line of defense against using Phortune to validate a
      // large list of stolen credit card numbers.

      PhabricatorSystemActionEngine::willTakeAction(
        array($viewer->getPHID()),
        new PhortuneAddPaymentMethodAction(),
        1);

      if (!$errors) {
        $errors = $this->processClientErrors(
          $provider,
          $request->getStr('errors'));
      }

      if (!$errors) {
        $client_token_raw = $request->getStr('token');
        $client_token = null;
        try {
          $client_token = phutil_json_decode($client_token_raw);
        } catch (PhutilJSONParserException $ex) {
          $errors[] = pht(
            'There was an error decoding token information submitted by the '.
            'client. Expected a JSON-encoded token dictionary, received: %s.',
            nonempty($client_token_raw, pht('nothing')));
        }

        if (!$provider->validateCreatePaymentMethodToken($client_token)) {
          $errors[] = pht(
            'There was an error with the payment token submitted by the '.
            'client. Expected a valid dictionary, received: %s.',
            $client_token_raw);
        }

        if (!$errors) {
          try {
            $provider->createPaymentMethodFromRequest(
              $request,
              $method,
              $client_token);
          } catch (PhortuneDisplayException $exception) {
            $display_exception = $exception;
          } catch (Exception $ex) {
            $errors = array(
              pht('There was an error adding this payment method:'),
              $ex->getMessage(),
            );
          }
        }
      }

      if (!$errors && !$display_exception) {
        $xactions = array();

        $xactions[] = $method->getApplicationTransactionTemplate()
          ->setTransactionType(PhabricatorTransactions::TYPE_CREATE)
          ->setNewValue(true);

        $editor = id(new PhortunePaymentMethodEditor())
          ->setActor($viewer)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true)
          ->setContinueOnMissingFields(true);

        $editor->applyTransactions($method, $xactions);

        $next_uri = new PhutilURI($next_uri);

        // If we added this method on a cart flow, return to the cart to
        // checkout with this payment method selected.
        if ($cart_id) {
          $next_uri->replaceQueryParam('paymentMethodID', $method->getID());
        }

        return id(new AphrontRedirectResponse())->setURI($next_uri);
      } else {
        if ($display_exception) {
          $dialog_body = $display_exception->getView();
        } else {
          $dialog_body = id(new PHUIInfoView())
            ->setErrors($errors);
        }

        return $this->newDialog()
          ->setTitle(pht('Error Adding Payment Method'))
          ->appendChild($dialog_body)
          ->addCancelButton($request->getRequestURI());
      }
    }

    $form = $provider->renderCreatePaymentMethodForm($request, $errors);

    $form
      ->setViewer($viewer)
      ->setAction($request->getPath())
      ->setWorkflow(true)
      ->addHiddenInput('isProviderForm', true)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Add Payment Method'))
          ->addCancelButton($next_uri));

    foreach ($state_params as $key => $value) {
      $form->addHiddenInput($key, $value);
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Method'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Add Payment Method'))
      ->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Add Payment Method'))
      ->setHeaderIcon('fa-plus-square');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $box,
        ));

    return $this->newPage()
      ->setTitle($provider->getPaymentMethodDescription())
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

  private function processClientErrors(
    PhortunePaymentProvider $provider,
    $client_errors_raw) {

    $errors = array();

    $client_errors = null;
    try {
      $client_errors = phutil_json_decode($client_errors_raw);
    } catch (PhutilJSONParserException $ex) {
      $errors[] = pht(
        'There was an error decoding error information submitted by the '.
        'client. Expected a JSON-encoded list of error codes, received: %s.',
        nonempty($client_errors_raw, pht('nothing')));
    }

    foreach (array_unique($client_errors) as $key => $client_error) {
      $client_errors[$key] = $provider->translateCreatePaymentMethodErrorCode(
        $client_error);
    }

    foreach (array_unique($client_errors) as $client_error) {
      switch ($client_error) {
        case PhortuneErrCode::ERR_CC_INVALID_NUMBER:
          $message = pht(
            'The card number you entered is not a valid card number. Check '.
            'that you entered it correctly.');
          break;
        case PhortuneErrCode::ERR_CC_INVALID_CVC:
          $message = pht(
            'The CVC code you entered is not a valid CVC code. Check that '.
            'you entered it correctly. The CVC code is a 3-digit or 4-digit '.
            'numeric code which usually appears on the back of the card.');
          break;
        case PhortuneErrCode::ERR_CC_INVALID_EXPIRY:
          $message = pht(
            'The card expiration date is not a valid expiration date. Check '.
            'that you entered it correctly. You can not add an expired card '.
            'as a payment method.');
          break;
        default:
          $message = $provider->getCreatePaymentMethodErrorMessage(
            $client_error);
          if (!$message) {
            $message = pht(
              "There was an unexpected error ('%s') processing payment ".
              "information.",
              $client_error);

            phlog($message);
          }
          break;
      }

      $errors[$client_error] = $message;
    }

    return $errors;
  }

  private function newMerchantMenu(array $merchants) {
    assert_instances_of($merchants, 'PhortuneMerchant');

    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $menu = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setBig(true)
      ->setFlush(true);

    foreach ($merchants as $merchant) {
      $merchant_uri = id(new PhutilURI($request->getRequestURI()))
        ->replaceQueryParam('merchantID', $merchant->getID());

      $item = id(new PHUIObjectItemView())
        ->setObjectName($merchant->getObjectName())
        ->setHeader($merchant->getName())
        ->setHref($merchant_uri)
        ->setClickable(true)
        ->setImageURI($merchant->getProfileImageURI());

      $menu->addItem($item);
    }

    return $menu;
  }

  private function newProviderMenu(array $providers, PhutilURI $state_uri) {
    assert_instances_of($providers, 'PhortunePaymentProvider');

    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $menu = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setBig(true)
      ->setFlush(true);

    foreach ($providers as $provider) {
      $provider_id = $provider->getProviderConfig()->getID();

      $provider_uri = id(clone $state_uri)
        ->replaceQueryParam('providerID', $provider_id);

      $description = $provider->getPaymentMethodDescription();
      $icon_uri = $provider->getPaymentMethodIcon();
      $details = $provider->getPaymentMethodProviderDescription();

      $icon = id(new PHUIIconView())
        ->setSpriteSheet(PHUIIconView::SPRITE_LOGIN)
        ->setSpriteIcon($icon_uri);

      $item = id(new PHUIObjectItemView())
        ->setHeader($description)
        ->setHref($provider_uri)
        ->setClickable(true)
        ->addAttribute($details)
        ->setImageIcon($icon);

      $menu->addItem($item);
    }

    return $menu;
  }

}
