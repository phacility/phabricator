<?php

final class PhortuneSubscriptionViewController extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $subscription = id(new PhortuneSubscriptionQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->needTriggers(true)
      ->executeOne();
    if (!$subscription) {
      return new Aphront404Response();
    }

    $is_merchant = (bool)$request->getURIData('merchantID');
    $merchant = $subscription->getMerchant();
    $account = $subscription->getAccount();

    $title = pht('Subscription: %s', $subscription->getSubscriptionName());

    $header = id(new PHUIHeaderView())
      ->setHeader($subscription->getSubscriptionName());

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObjectURI($request->getRequestURI());

    $crumbs = $this->buildApplicationCrumbs();
    if ($is_merchant) {
      $this->addMerchantCrumb($crumbs, $merchant);
    } else {
      $this->addAccountCrumb($crumbs, $account);
    }
    $crumbs->addTextCrumb(pht('Subscription %d', $subscription->getID()));

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $next_invoice = $subscription->getTrigger()->getNextEventPrediction();
    $properties->addProperty(
      pht('Next Invoice'),
      phabricator_datetime($next_invoice, $viewer));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $carts = id(new PhortuneCartQuery())
      ->setViewer($viewer)
      ->withSubscriptionPHIDs(array($subscription->getPHID()))
      ->needPurchases(true)
      ->withStatuses(
        array(
          PhortuneCart::STATUS_PURCHASING,
          PhortuneCart::STATUS_CHARGED,
          PhortuneCart::STATUS_HOLD,
          PhortuneCart::STATUS_REVIEW,
          PhortuneCart::STATUS_PURCHASED,
        ))
      ->execute();

    $phids = array();
    foreach ($carts as $cart) {
      $phids[] = $cart->getPHID();
      foreach ($cart->getPurchases() as $purchase) {
        $phids[] = $purchase->getPHID();
      }
    }
    $handles = $this->loadViewerHandles($phids);

    $invoice_table = id(new PhortuneOrderTableView())
      ->setUser($viewer)
      ->setCarts($carts)
      ->setHandles($handles);

    $account_id = $account->getID();
    $merchant_id = $merchant->getID();
    $subscription_id = $subscription->getID();

    if ($is_merchant) {
      $invoices_uri = $this->getApplicationURI(
        "merchant/{$merchant_id}/subscription/order/{$subscription_id}/");
    } else {
      $invoices_uri = $this->getApplicationURI(
        "{$account_id}/subscription/order/{$subscription_id}/");
    }

    $invoice_header = id(new PHUIHeaderView())
      ->setHeader(pht('Recent Invoices'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setIcon(
            id(new PHUIIconView())
              ->setIconFont('fa-list'))
          ->setHref($invoices_uri)
          ->setText(pht('View All Invoices')));

    $invoice_box = id(new PHUIObjectBoxView())
      ->setHeader($invoice_header)
      ->appendChild($invoice_table);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $invoice_box,
      ),
      array(
        'title' => $title,
      ));
  }

}
