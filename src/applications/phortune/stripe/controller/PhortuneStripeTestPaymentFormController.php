<?php

final class PhortuneStripeTestPaymentFormController
extends PhortuneStripeBaseController {
  public function processRequest() {
    $request               = $this->getRequest();
    $user                  = $request->getUser();
    $title                 = 'Test Payment Form';
    $error_view            = null;
    $card_number_error     = null;
    $card_cvc_error        = null;
    $card_expiration_error = null;
    $stripe_key            = $request->getStr('stripeKey');
    if (!$stripe_key) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Missing stripeKey parameter in URI');
    }

    if (!$error_view && $request->isFormPost()) {
      $card_errors  = $request->getStr('cardErrors');
      $stripe_token = $request->getStr('stripeToken');
      if ($card_errors) {
        $raw_errors = json_decode($card_errors);
        list($card_number_error,
             $card_cvc_error,
             $card_expiration_error,
             $messages) = $this->parseRawErrors($raw_errors);
        $error_view = id(new AphrontErrorView())
          ->setTitle('There were errors processing your card.')
          ->setErrors($messages);
      } else if (!$stripe_token) {
        // this shouldn't happen, so show the user a very generic error
        // message and log that this error occurred...!
        $error_view = id(new AphrontErrorView())
          ->setTitle('There was an unknown error processing your card.')
          ->setErrors(array('Please try again.'));
        $error = 'payment form submitted but no stripe token and no errors';
        $this->logStripeError($error);
      } else {
        // success -- do something with $stripe_token!!
      }
    } else if (!$error_view) {
      $error_view = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setTitle(
          'If you are using a test stripe key, use 4242424242424242, '.
          'any three digits for CVC, and any valid expiration date to '.
          'test!');
    }

    $view = id(new AphrontPanelView())
      ->setWidth(AphrontPanelView::WIDTH_FORM)
      ->setHeader($title);

    $form = id(new PhortuneStripePaymentFormView())
      ->setUser($user)
      ->setStripeKey($stripe_key)
      ->setCardNumberError($card_number_error)
      ->setCardCVCError($card_cvc_error)
      ->setCardExpirationError($card_expiration_error);

    $view->appendChild($form);

    return
      $this->buildStandardPageResponse(
        array(
          $error_view,
          $view,
        ),
        array(
          'title' => $title,
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
          $card_number_error = true;
          break;
        case 'cvc':
        case 'invalid_cvc':
        case 'incorrect_cvc':
          $card_cvc_error = true;
          break;
        case 'expiry':
        case 'invalid_expiry_month':
        case 'invalid_expiry_year':
          $card_expiration_error = true;
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

    // append a helpful "fix this" to the messages to be displayed to the user
    $messages[] = pht(
      'Please fix these errors and try again.',
      count($messages));

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
