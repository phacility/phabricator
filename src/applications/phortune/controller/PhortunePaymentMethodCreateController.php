<?php

final class PhortunePaymentMethodCreateController
  extends PhortuneController {

  private $accountID;

  public function willProcessRequest(array $data) {
    $this->accountID = $data['accountID'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $account = id(new PhortuneAccountQuery())
      ->setViewer($user)
      ->withIDs(array($this->accountID))
      ->executeOne();
    if (!$account) {
      return new Aphront404Response();
    }

    $cancel_uri = $this->getApplicationURI($account->getID().'/');
    $account_uri = $this->getApplicationURI($account->getID().'/');

    $providers = PhortunePaymentProvider::getProvidersForAddPaymentMethod();
    if (!$providers) {
      throw new Exception(
        'There are no payment providers enabled that can add payment '.
        'methods.');
    }

    $provider_key = $request->getStr('providerKey');
    if (empty($providers[$provider_key])) {
      $choices = array();
      foreach ($providers as $provider) {
        $choices[] = $this->renderSelectProvider($provider);
      }

      $content = phutil_tag(
        'div',
        array(
          'class' => 'phortune-payment-method-list',
        ),
        $choices);

      return $this->newDialog()
        ->setRenderDialogAsDiv(true)
        ->setTitle(pht('Add Payment Method'))
        ->appendParagraph(pht('Choose a payment method to add:'))
        ->appendChild($content)
        ->addCancelButton($account_uri);
    }

    $provider = $providers[$provider_key];

    $errors = array();
    if ($request->isFormPost() && $request->getBool('isProviderForm')) {
      $method = id(new PhortunePaymentMethod())
        ->setAccountPHID($account->getPHID())
        ->setAuthorPHID($user->getPHID())
        ->setStatus(PhortunePaymentMethod::STATUS_ACTIVE)
        ->setProviderType($provider->getProviderType())
        ->setProviderDomain($provider->getProviderDomain());

      if (!$errors) {
        $errors = $this->processClientErrors(
          $provider,
          $request->getStr('errors'));
      }

      if (!$errors) {
        $client_token_raw = $request->getStr('token');
        $client_token = json_decode($client_token_raw, true);
        if (!is_array($client_token)) {
          $errors[] = pht(
            'There was an error decoding token information submitted by the '.
            'client. Expected a JSON-encoded token dictionary, received: %s.',
            nonempty($client_token_raw, pht('nothing')));
        } else {
          if (!$provider->validateCreatePaymentMethodToken($client_token)) {
            $errors[] = pht(
              'There was an error with the payment token submitted by the '.
              'client. Expected a valid dictionary, received: %s.',
              $client_token_raw);
          }
        }
        if (!$errors) {
          $errors = $provider->createPaymentMethodFromRequest(
            $request,
            $method,
            $client_token);
        }
      }

      if (!$errors) {
        $method->save();

        $save_uri = new PhutilURI($account_uri);
        $save_uri->setFragment('payment');
        return id(new AphrontRedirectResponse())->setURI($save_uri);
      } else {
        $dialog = id(new AphrontDialogView())
          ->setUser($user)
          ->setTitle(pht('Error Adding Payment Method'))
          ->appendChild(id(new AphrontErrorView())->setErrors($errors))
          ->addCancelButton($request->getRequestURI());

        return id(new AphrontDialogResponse())->setDialog($dialog);
      }
    }

    $form = $provider->renderCreatePaymentMethodForm($request, $errors);

    $form
      ->setUser($user)
      ->setAction($request->getRequestURI())
      ->setWorkflow(true)
      ->addHiddenInput('providerKey', $provider_key)
      ->addHiddenInput('isProviderForm', true)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Add Payment Method'))
          ->addCancelButton($account_uri));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($provider->getPaymentMethodDescription())
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Add Payment Method'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $provider->getPaymentMethodDescription(),
      ));
  }

  private function renderSelectProvider(
    PhortunePaymentProvider $provider) {

    $request = $this->getRequest();
    $user = $request->getUser();

    $description = $provider->getPaymentMethodDescription();
    $icon_uri = $provider->getPaymentMethodIcon();
    $details = $provider->getPaymentMethodProviderDescription();

    $this->requireResource('phortune-css');

    $icon = id(new PHUIIconView())
      ->setImage($icon_uri)
      ->addClass('phortune-payment-icon');

    $button = id(new PHUIButtonView())
      ->setSize(PHUIButtonView::BIG)
      ->setColor(PHUIButtonView::GREY)
      ->setIcon($icon)
      ->setText($description)
      ->setSubtext($details);

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->addHiddenInput('providerKey', $provider->getProviderKey())
      ->appendChild($button);

    return $form;
  }

  private function processClientErrors(
    PhortunePaymentProvider $provider,
    $client_errors_raw) {

    $errors = array();

    $client_errors = json_decode($client_errors_raw, true);
    if (!is_array($client_errors)) {
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

}
