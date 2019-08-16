<?php

final class PhortunePaymentMethodDisableController
  extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $method_id = $request->getURIData('id');

    $method = id(new PhortunePaymentMethodQuery())
      ->setViewer($viewer)
      ->withIDs(array($method_id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$method) {
      return new Aphront404Response();
    }

    if ($method->getStatus() == PhortunePaymentMethod::STATUS_DISABLED) {
      return new Aphront400Response();
    }

    $subscription_id = $request->getInt('subscriptionID');
    if ($subscription_id) {
      $subscription = id(new PhortuneSubscriptionQuery())
        ->setViewer($viewer)
        ->withIDs(array($subscription_id))
        ->withAccountPHIDs(array($method->getAccountPHID()))
        ->withMerchantPHIDs(array($method->getMerchantPHID()))
        ->executeOne();
      if (!$subscription) {
        return new Aphront404Response();
      }
    } else {
      $subscription = null;
    }

    $account = $method->getAccount();
    $account_id = $account->getID();
    $account_uri = $account->getPaymentMethodsURI();

    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = $method->getApplicationTransactionTemplate()
        ->setTransactionType(
          PhortunePaymentMethodStatusTransaction::TRANSACTIONTYPE)
        ->setNewValue(PhortunePaymentMethod::STATUS_DISABLED);

      $editor = id(new PhortunePaymentMethodEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      $editor->applyTransactions($method, $xactions);

      if ($subscription) {
        $next_uri = $subscription->getURI();
      } else {
        $next_uri = $account_uri;
      }

      return id(new AphrontRedirectResponse())->setURI($next_uri);
    }

    $method_phid = $method->getPHID();
    $handles = $viewer->loadHandles(
      array(
        $method_phid,
      ));

    $method_handle = $handles[$method_phid];
    $method_display = $method_handle->renderLink();
    $method_display = phutil_tag('strong', array(), $method_display);

    return $this->newDialog()
      ->setTitle(pht('Remove Payment Method'))
      ->addHiddenInput('subscriptionID', $subscription_id)
      ->appendParagraph(
        pht(
          'Remove the payment method %s from your account?',
          $method_display))
      ->appendParagraph(
        pht(
          'You will no longer be able to make payments using this payment '.
          'method.'))
      ->addCancelButton($account_uri)
      ->addSubmitButton(pht('Remove Payment Method'));
  }

}
