<?php

final class PhortunePaymentMethodEditController
  extends PhortuneController {

  private $accountID;

  public function willProcessRequest(array $data) {
    $this->accountID = $data['accountID'];
  }

  /**
   * @phutil-external-symbol class Stripe_Token
   * @phutil-external-symbol class Stripe_Customer
   */
  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $stripe_publishable_key = PhabricatorEnv::getEnvConfig(
      'stripe.publishable-key');
    if (!$stripe_publishable_key) {
      throw new Exception(
        "Stripe publishable API key (`stripe.publishable-key`) is ".
        "not configured.");
    }

    $stripe_secret_key = PhabricatorEnv::getEnvConfig('stripe.secret-key');
    if (!$stripe_secret_key) {
      throw new Exception(
        "Stripe secret API kye (`stripe.secret-key`) is not configured.");
    }

    $account = id(new PhortuneAccountQuery())
      ->setViewer($user)
      ->withIDs(array($this->accountID))
      ->executeOne();
    if (!$account) {
      return new Aphront404Response();
    }

    $account_uri = $this->getApplicationURI($account->getID().'/');

    $e_card_number = true;
    $e_card_cvc = true;
    $e_card_exp = true;

    $errors = array();
    if ($request->isFormPost()) {
      $card_errors = $request->getStr('cardErrors');
      $stripe_token = $request->getStr('stripeToken');
      if ($card_errors) {
        $raw_errors = json_decode($card_errors);
        list($e_card_number,
             $e_card_cvc,
             $e_card_exp,
             $messages) = $this->parseRawErrors($raw_errors);
        $errors = array_merge($errors, $messages);
      } else if (!$stripe_token) {
        $errors[] = pht('There was an unknown error processing your card.');
      }

      if (!$errors) {
        $root = dirname(phutil_get_library_root('phabricator'));
        require_once $root.'/externals/stripe-php/lib/Stripe.php';

        try {
          // First, make sure the token is valid.
          $info = id(new Stripe_Token())
            ->retrieve($stripe_token, $stripe_secret_key);

          // Then, we need to create a Customer in order to be able to charge
          // the card more than once. We create one Customer for each card;
          // they do not map to PhortuneAccounts because we allow an account to
          // have more than one active card.
          $customer = Stripe_Customer::create(
            array(
              'card' => $stripe_token,
              'description' => $account->getPHID().':'.$user->getUserName(),
            ), $stripe_secret_key);

          $card = $info->card;
        } catch (Exception $ex) {
          phlog($ex);
          $errors[] = pht(
            'There was an error communicating with the payments backend.');
        }

        if (!$errors) {
          $payment_method = id(new PhortunePaymentMethod())
            ->setAccountPHID($account->getPHID())
            ->setAuthorPHID($user->getPHID())
            ->setName($card->type.' / '.$card->last4)
            ->setStatus(PhortunePaymentMethod::STATUS_ACTIVE)
            ->setExpiresEpoch(strtotime($card->exp_year.'-'.$card->exp_month))
            ->setMetadata(
              array(
                'type'              => 'stripe.customer',
                'stripeCustomerID'  => $customer->id,
                'stripeTokenID'     => $stripe_token,
              ))
            ->save();

          $save_uri = new PhutilURI($account_uri);
          $save_uri->setFragment('payment');

          return id(new AphrontRedirectResponse())->setURI($save_uri);
        }
      }

      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle(pht('Error Adding Card'))
        ->appendChild(id(new AphrontErrorView())->setErrors($errors))
        ->addCancelButton($request->getRequestURI());

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    if ($errors) {
      $errors = id(new AphrontErrorView())
        ->setErrors($errors);
    }

    $header = id(new PhabricatorHeaderView())
      ->setHeader(pht('Add New Payment Method'));

    $form_id = celerity_generate_unique_node_id();
    require_celerity_resource('stripe-payment-form-css');
    require_celerity_resource('aphront-tooltip-css');
    Javelin::initBehavior('phabricator-tooltips');

    $form = id(new AphrontFormView())
      ->setID($form_id)
      ->setUser($user)
      ->setWorkflow(true)
      ->setAction($request->getRequestURI())
      ->appendChild(
        id(new AphrontFormMarkupControl())
        ->setLabel('')
        ->setValue(
          javelin_tag(
            'div',
            array(
              'class' => 'credit-card-logos',
              'sigil' => 'has-tooltip',
              'meta' => array(
                'tip'  => 'We support Visa, Mastercard, American Express, '.
                          'Discover, JCB, and Diners Club.',
                'size' => 440,
              )
            ))))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('Card Number')
        ->setDisableAutocomplete(true)
        ->setSigil('number-input')
        ->setError($e_card_number))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('CVC')
        ->setDisableAutocomplete(true)
        ->setSigil('cvc-input')
        ->setError($e_card_cvc))
      ->appendChild(
        id(new PhortuneMonthYearExpiryControl())
        ->setLabel('Expiration')
        ->setUser($user)
        ->setError($e_card_exp))
      ->appendChild(
        javelin_tag(
          'input',
          array(
            'hidden' => true,
            'name'   => 'stripeToken',
            'sigil'  => 'stripe-token-input',
          )))
      ->appendChild(
        javelin_tag(
          'input',
          array(
            'hidden' => true,
            'name'   => 'cardErrors',
            'sigil'  => 'card-errors-input'
          )))
      ->appendChild(
        phutil_tag(
          'input',
          array(
            'hidden' => true,
            'name'   => 'stripeKey',
            'value'  => $stripe_publishable_key,
          )))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue('Add Payment Method')
        ->addCancelButton($account_uri));

    Javelin::initBehavior(
      'stripe-payment-form',
      array(
        'stripePublishKey' => $stripe_publishable_key,
        'root'             => $form_id,
      ));

    $title = pht('Add Payment Method');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Account'))
        ->setHref($account_uri));
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Payment Methods'))
        ->setHref($request->getRequestURI()));

    return
      $this->buildStandardPageResponse(
        array(
          $crumbs,
          $header,
          $errors,
          $form,
        ),
        array(
          'title' => $title,
          'device' => true,
          'dust' => true,
        ));
  }

  /**
   * Stripe JS and calls to Stripe handle all errors with processing this
   * form. This function takes the raw errors - in the form of an array
   * where each elementt is $type => $message - and figures out what if
   * any fields were invalid and pulls the messages into a flat object.
   *
   * See https://stripe.com/docs/api#errors for more information on possible
   * errors.
   */
  private function parseRawErrors($errors) {
    $card_number_error     = null;
    $card_cvc_error        = null;
    $card_expiration_error = null;
    $messages              = array();
    foreach ($errors as $index => $error) {
      $type       = key($error);
      $msg        = reset($error);
      $messages[] = $msg;
      switch ($type) {
        case 'number':
        case 'invalid_number':
        case 'incorrect_number':
          $card_number_error = pht('Invalid');
          break;
        case 'cvc':
        case 'invalid_cvc':
        case 'incorrect_cvc':
          $card_cvc_error = pht('Invalid');
          break;
        case 'expiry':
        case 'invalid_expiry_month':
        case 'invalid_expiry_year':
          $card_expiration_error = pht('Invalid');
          break;
        case 'card_declined':
        case 'expired_card':
        case 'duplicate_transaction':
        case 'processing_error':
          // these errors don't map well to field(s) being bad
          break;
        case 'invalid_amount':
        case 'missing':
        default:
          // these errors only happen if we (not the user) messed up so log it
          $error = sprintf(
            'error_type: %s error_message: %s',
            $type,
            $msg);
          $this->logStripeError($error);
          break;
      }
    }

    return array(
      $card_number_error,
      $card_cvc_error,
      $card_expiration_error,
      $messages
    );
  }

  private function logStripeError($message) {
    phlog('STRIPE-ERROR '.$message);
  }

}
