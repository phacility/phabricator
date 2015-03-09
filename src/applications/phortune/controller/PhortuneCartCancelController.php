<?php

final class PhortuneCartCancelController
  extends PhortuneCartController {

  private $id;
  private $action;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
    $this->action = $data['action'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $authority = $this->loadMerchantAuthority();

    $cart_query = id(new PhortuneCartQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->needPurchases(true);

    if ($authority) {
      $cart_query->withMerchantPHIDs(array($authority->getPHID()));
    }

    $cart = $cart_query->executeOne();
    if (!$cart) {
      return new Aphront404Response();
    }

    switch ($this->action) {
      case 'cancel':
        // You must be able to edit the account to cancel an order.
        PhabricatorPolicyFilter::requireCapability(
          $viewer,
          $cart->getAccount(),
          PhabricatorPolicyCapability::CAN_EDIT);
        $is_refund = false;
        break;
      case 'refund':
        // You must be able to control the merchant to refund an order.
        PhabricatorPolicyFilter::requireCapability(
          $viewer,
          $cart->getMerchant(),
          PhabricatorPolicyCapability::CAN_EDIT);
        $is_refund = true;
        break;
      default:
        return new Aphront404Response();
    }

    $cancel_uri = $cart->getDetailURI($authority);
    $merchant = $cart->getMerchant();

    try {
      if ($is_refund) {
        $title = pht('Unable to Refund Order');
        $cart->assertCanRefundOrder();
      } else {
        $title = pht('Unable to Cancel Order');
        $cart->assertCanCancelOrder();
      }
    } catch (Exception $ex) {
      return $this->newDialog()
        ->setTitle($title)
        ->appendChild($ex->getMessage())
        ->addCancelButton($cancel_uri);
    }

    $charges = id(new PhortuneChargeQuery())
      ->setViewer($viewer)
      ->withCartPHIDs(array($cart->getPHID()))
      ->withStatuses(
        array(
          PhortuneCharge::STATUS_HOLD,
          PhortuneCharge::STATUS_CHARGED,
        ))
      ->execute();

    $amounts = mpull($charges, 'getAmountAsCurrency');
    $maximum = PhortuneCurrency::newFromList($amounts);
    $v_refund = $maximum->formatForDisplay();

    $errors = array();
    $e_refund = true;
    if ($request->isFormPost()) {
      if ($is_refund) {
        try {
          $refund = PhortuneCurrency::newFromUserInput(
            $viewer,
            $request->getStr('refund'));
          $refund->assertInRange('0.00 USD', $maximum->formatForDisplay());
        } catch (Exception $ex) {
          $errors[] = $ex->getMessage();
          $e_refund = pht('Invalid');
        }
      } else {
        $refund = $maximum;
      }

      if (!$errors) {
        $charges = msort($charges, 'getID');
        $charges = array_reverse($charges);

        if ($charges) {
          $providers = id(new PhortunePaymentProviderConfigQuery())
            ->setViewer($viewer)
            ->withPHIDs(mpull($charges, 'getProviderPHID'))
            ->execute();
          $providers = mpull($providers, null, 'getPHID');
        } else {
          $providers = array();
        }

        foreach ($charges as $charge) {
          $refundable = $charge->getAmountRefundableAsCurrency();
          if (!$refundable->isPositive()) {
            // This charge is a refund, or has already been fully refunded.
            continue;
          }

          if ($refund->isGreaterThan($refundable)) {
            $refund_amount = $refundable;
          } else {
            $refund_amount = $refund;
          }

          $provider_config = idx($providers, $charge->getProviderPHID());
          if (!$provider_config) {
            throw new Exception(pht('Unable to load provider for charge!'));
          }

          $provider = $provider_config->buildProvider();

          $refund_charge = $cart->willRefundCharge(
            $viewer,
            $provider,
            $charge,
            $refund_amount);

          $refunded = false;
          try {
            $provider->refundCharge($charge, $refund_charge);
            $refunded = true;
          } catch (Exception $ex) {
            phlog($ex);
            $cart->didFailRefund($charge, $refund_charge);
          }

          if ($refunded) {
            $cart->didRefundCharge($charge, $refund_charge);
            $refund = $refund->subtract($refund_amount);
          }

          if (!$refund->isPositive()) {
            break;
          }
        }

        if ($refund->isPositive()) {
          throw new Exception(pht('Unable to refund some charges!'));
        }

        // TODO: If every HOLD and CHARGING transaction has been fully refunded
        // and we're in a HOLD, REVIEW, PURCHASING or CHARGED cart state we
        // probably need to kick the cart back to READY here (or maybe kill
        // it if it was in REVIEW)?

        return id(new AphrontRedirectResponse())->setURI($cancel_uri);
      }
    }

    if ($is_refund) {
      $title = pht('Refund Order?');
      $body = pht(
        'Really refund this order?');
      $button = pht('Refund Order');
      $cancel_text = pht('Cancel');

      $form = id(new AphrontFormView())
        ->setUser($viewer)
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setName('refund')
            ->setLabel(pht('Amount'))
            ->setError($e_refund)
            ->setValue($v_refund));

      $form = $form->buildLayoutView();

    } else {
      $title = pht('Cancel Order?');
      $body = pht(
        'Really cancel this order? Any payment will be refunded.');
      $button = pht('Cancel Order');

      // Don't give the user a "Cancel" button in response to a "Cancel?"
      // prompt, as it's confusing.
      $cancel_text = pht('Do Not Cancel Order');

      $form = null;
    }

    return $this->newDialog()
      ->setTitle($title)
      ->setErrors($errors)
      ->appendChild($body)
      ->appendChild($form)
      ->addSubmitButton($button)
      ->addCancelButton($cancel_uri, $cancel_text);
  }
}
