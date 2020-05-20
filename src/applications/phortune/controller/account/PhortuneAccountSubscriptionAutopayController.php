<?php

final class PhortuneAccountSubscriptionAutopayController
  extends PhortuneAccountController {

  protected function shouldRequireAccountEditCapability() {
    return true;
  }

  protected function handleAccountRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $account = $this->getAccount();

    $subscription = id(new PhortuneSubscriptionQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('subscriptionID')))
      ->withAccountPHIDs(array($account->getPHID()))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$subscription) {
      return new Aphront404Response();
    }

    $method = id(new PhortunePaymentMethodQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('methodID')))
      ->withAccountPHIDs(array($subscription->getAccountPHID()))
      ->withMerchantPHIDs(array($subscription->getMerchantPHID()))
      ->withStatuses(
        array(
          PhortunePaymentMethod::STATUS_ACTIVE,
        ))
      ->executeOne();
    if (!$method) {
      return new Aphront404Response();
    }

    $next_uri = $subscription->getURI();

    $autopay_phid = $subscription->getDefaultPaymentMethodPHID();
    $is_stop = ($autopay_phid === $method->getPHID());

    if ($request->isFormOrHisecPost()) {
      if ($is_stop) {
        $new_phid = null;
      } else {
        $new_phid = $method->getPHID();
      }

      $xactions = array();

      $xactions[] = $subscription->getApplicationTransactionTemplate()
        ->setTransactionType(
          PhortuneSubscriptionAutopayTransaction::TRANSACTIONTYPE)
        ->setNewValue($new_phid);

      $editor = $subscription->getApplicationTransactionEditor()
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setCancelURI($next_uri);

      $editor->applyTransactions($subscription, $xactions);

      return id(new AphrontRedirectResponse())->setURI($next_uri);
    }

    $method_phid = $method->getPHID();
    $subscription_phid = $subscription->getPHID();

    $handles = $viewer->loadHandles(
      array(
        $method_phid,
        $subscription_phid,
      ));

    $method_handle = $handles[$method_phid];
    $subscription_handle = $handles[$subscription_phid];

    $method_display = $method_handle->renderLink();
    $method_display = phutil_tag(
      'strong',
      array(),
      $method_display);

    $subscription_display = $subscription_handle->renderLink();
    $subscription_display = phutil_tag(
      'strong',
      array(),
      $subscription_display);

    $body = array();
    if ($is_stop) {
      $title = pht('Stop Autopay');

      $body[] = pht(
        'Remove %s as the automatic payment method for subscription %s?',
        $method_display,
        $subscription_display);

      $body[] = pht(
        'This payment method will no longer be charged automatically.');

      $submit = pht('Stop Autopay');
    } else {
      $title = pht('Start Autopay');

      $body[] = pht(
        'Set %s as the automatic payment method for subscription %s?',
        $method_display,
        $subscription_display);

      $body[] = pht(
        'This payment method will be used to automatically pay future '.
        'charges.');

      $submit = pht('Start Autopay');
    }

    $dialog = $this->newDialog()
      ->setTitle($title)
      ->addCancelButton($next_uri)
      ->addSubmitButton($submit);

    foreach ($body as $graph) {
      $dialog->appendParagraph($graph);
    }

    return $dialog;
  }

}
