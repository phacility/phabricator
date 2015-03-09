<?php

final class PhortuneSubscriptionViewController extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $authority = $this->loadMerchantAuthority();

    $subscription_query = id(new PhortuneSubscriptionQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->needTriggers(true);

    if ($authority) {
      $subscription_query->withMerchantPHIDs(array($authority->getPHID()));
    }

    $subscription = $subscription_query->executeOne();
    if (!$subscription) {
      return new Aphront404Response();
    }

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $subscription,
      PhabricatorPolicyCapability::CAN_EDIT);

    $merchant = $subscription->getMerchant();
    $account = $subscription->getAccount();

    $account_id = $account->getID();
    $subscription_id = $subscription->getID();

    $title = $subscription->getSubscriptionFullName();

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObjectURI($request->getRequestURI());

    $edit_uri = $subscription->getEditURI();

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Subscription'))
        ->setHref($edit_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));


    $crumbs = $this->buildApplicationCrumbs();
    if ($authority) {
      $this->addMerchantCrumb($crumbs, $merchant);
    } else {
      $this->addAccountCrumb($crumbs, $account);
    }
    $crumbs->addTextCrumb($subscription->getSubscriptionCrumbName());

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $next_invoice = $subscription->getTrigger()->getNextEventPrediction();
    $properties->addProperty(
      pht('Next Invoice'),
      phabricator_datetime($next_invoice, $viewer));

    $default_method = $subscription->getDefaultPaymentMethodPHID();
    if ($default_method) {
      $handles = $this->loadViewerHandles(array($default_method));
      $autopay_method = $handles[$default_method]->renderLink();
    } else {
      $autopay_method = phutil_tag(
        'em',
        array(),
        pht('No Autopay Method Configured'));
    }

    $properties->addProperty(
      pht('Autopay With'),
      $autopay_method);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $due_box = $this->buildDueInvoices($subscription, $authority);
    $invoice_box = $this->buildPastInvoices($subscription, $authority);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $due_box,
        $invoice_box,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildDueInvoices(
    PhortuneSubscription $subscription,
    $authority) {
    $viewer = $this->getViewer();

    $invoices = id(new PhortuneCartQuery())
      ->setViewer($viewer)
      ->withSubscriptionPHIDs(array($subscription->getPHID()))
      ->needPurchases(true)
      ->withInvoices(true)
      ->execute();

    $phids = array();
    foreach ($invoices as $invoice) {
      $phids[] = $invoice->getPHID();
      $phids[] = $invoice->getMerchantPHID();
      foreach ($invoice->getPurchases() as $purchase) {
        $phids[] = $purchase->getPHID();
      }
    }
    $handles = $this->loadViewerHandles($phids);

    $invoice_table = id(new PhortuneOrderTableView())
      ->setUser($viewer)
      ->setCarts($invoices)
      ->setIsInvoices(true)
      ->setIsMerchantView((bool)$authority)
      ->setHandles($handles);

    $invoice_header = id(new PHUIHeaderView())
      ->setHeader(pht('Invoices Due'));

    return id(new PHUIObjectBoxView())
      ->setHeader($invoice_header)
      ->appendChild($invoice_table);
  }

  private function buildPastInvoices(
    PhortuneSubscription $subscription,
    $authority) {
    $viewer = $this->getViewer();

    $invoices = id(new PhortuneCartQuery())
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
      ->setLimit(50)
      ->execute();

    $phids = array();
    foreach ($invoices as $invoice) {
      $phids[] = $invoice->getPHID();
      foreach ($invoice->getPurchases() as $purchase) {
        $phids[] = $purchase->getPHID();
      }
    }
    $handles = $this->loadViewerHandles($phids);

    $invoice_table = id(new PhortuneOrderTableView())
      ->setUser($viewer)
      ->setCarts($invoices)
      ->setHandles($handles);

    $account = $subscription->getAccount();
    $merchant = $subscription->getMerchant();

    $account_id = $account->getID();
    $merchant_id = $merchant->getID();
    $subscription_id = $subscription->getID();

    if ($authority) {
      $invoices_uri = $this->getApplicationURI(
        "merchant/{$merchant_id}/subscription/order/{$subscription_id}/");
    } else {
      $invoices_uri = $this->getApplicationURI(
        "{$account_id}/subscription/order/{$subscription_id}/");
    }

    $invoice_header = id(new PHUIHeaderView())
      ->setHeader(pht('Past Invoices'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setIcon(
            id(new PHUIIconView())
              ->setIconFont('fa-list'))
          ->setHref($invoices_uri)
          ->setText(pht('View All Invoices')));

    return id(new PHUIObjectBoxView())
      ->setHeader($invoice_header)
      ->appendChild($invoice_table);
  }

}
