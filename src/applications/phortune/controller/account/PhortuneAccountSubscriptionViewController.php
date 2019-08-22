<?php

final class PhortuneAccountSubscriptionViewController
  extends PhortuneAccountController {

  protected function shouldRequireAccountEditCapability() {
    return false;
  }

  protected function handleAccountRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $subscription = id(new PhortuneSubscriptionQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('subscriptionID')))
      ->needTriggers(true)
      ->executeOne();
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
      ->setHeader($title)
      ->setHeaderIcon('fa-retweet');

    $edit_uri = $subscription->getEditURI();

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($subscription->getSubscriptionCrumbName())
      ->setBorder(true);

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $next_invoice = $subscription->getTrigger()->getNextEventPrediction();
    $properties->addProperty(
      pht('Next Invoice'),
      phabricator_datetime($next_invoice, $viewer));

    $autopay = $this->newAutopayView($subscription);

    $details = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Subscription Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addPropertyList($properties);

    $due_box = $this->buildDueInvoices($subscription);
    $invoice_box = $this->buildPastInvoices($subscription);

    $timeline = $this->buildTransactionTimeline(
      $subscription,
      new PhortuneSubscriptionTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $details,
          $autopay,
          $due_box,
          $invoice_box,
          $timeline,
        ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildDueInvoices(PhortuneSubscription $subscription) {
    $viewer = $this->getViewer();

    $invoices = id(new PhortuneCartQuery())
      ->setViewer($viewer)
      ->withSubscriptionPHIDs(array($subscription->getPHID()))
      ->needPurchases(true)
      ->withInvoices(true)
      ->execute();

    $invoice_table = id(new PhortuneOrderTableView())
      ->setUser($viewer)
      ->setCarts($invoices)
      ->setIsInvoices(true);

    $invoice_header = id(new PHUIHeaderView())
      ->setHeader(pht('Invoices Due'));

    return id(new PHUIObjectBoxView())
      ->setHeader($invoice_header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($invoice_table);
  }

  private function buildPastInvoices(PhortuneSubscription $subscription) {
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

    $invoice_table = id(new PhortuneOrderTableView())
      ->setUser($viewer)
      ->setCarts($invoices);

    $account = $subscription->getAccount();
    $merchant = $subscription->getMerchant();

    $account_id = $account->getID();
    $merchant_id = $merchant->getID();
    $subscription_id = $subscription->getID();

    $invoices_uri = $this->getApplicationURI(
      "{$account_id}/subscription/order/{$subscription_id}/");

    $invoice_header = id(new PHUIHeaderView())
      ->setHeader(pht('Past Invoices'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setIcon('fa-list')
          ->setHref($invoices_uri)
          ->setText(pht('View All Invoices')));

    return id(new PHUIObjectBoxView())
      ->setHeader($invoice_header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($invoice_table);
  }

  private function newAutopayView(PhortuneSubscription $subscription) {
    $viewer = $this->getViewer();
    $account = $subscription->getAccount();

    $add_method_uri = urisprintf(
      '/account/%d/methods/new/?subscriptionID=%s',
      $account->getID(),
      $subscription->getID());
    $add_method_uri = $this->getApplicationURI($add_method_uri);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $subscription,
      PhabricatorPolicyCapability::CAN_EDIT);

    $methods = id(new PhortunePaymentMethodQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($subscription->getAccountPHID()))
      ->withMerchantPHIDs(array($subscription->getMerchantPHID()))
      ->withStatuses(
        array(
          PhortunePaymentMethod::STATUS_ACTIVE,
        ))
      ->execute();
    $methods = mpull($methods, null, 'getPHID');

    $autopay_phid = $subscription->getDefaultPaymentMethodPHID();
    $autopay_method = idx($methods, $autopay_phid);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Autopay'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setIcon('fa-plus')
          ->setHref($add_method_uri)
          ->setText(pht('Add Payment Method'))
          ->setWorkflow(!$can_edit)
          ->setDisabled(!$can_edit));

    $methods = array_select_keys($methods, array($autopay_phid)) + $methods;

    $rows = array();
    $rowc = array();
    foreach ($methods as $method) {
      $is_autopay = ($autopay_method === $method);

      $remove_uri = urisprintf(
        '/card/%d/disable/?subscriptionID=%d',
        $method->getID(),
        $subscription->getID());
      $remove_uri = $this->getApplicationURI($remove_uri);

      $autopay_uri = urisprintf(
        '/account/%d/subscriptions/%d/autopay/%d/',
        $account->getID(),
        $subscription->getID(),
        $method->getID());
      $autopay_uri = $this->getApplicationURI($autopay_uri);

      $remove_button = id(new PHUIButtonView())
        ->setTag('a')
        ->setColor('grey')
        ->setIcon('fa-times')
        ->setText(pht('Delete'))
        ->setHref($remove_uri)
        ->setWorkflow(true)
        ->setDisabled(!$can_edit);

      if ($is_autopay) {
        $autopay_button = id(new PHUIButtonView())
          ->setColor('red')
          ->setIcon('fa-times')
          ->setText(pht('Stop Autopay'));
      } else {
        if ($autopay_method) {
          $make_color = 'grey';
        } else {
          $make_color = 'green';
        }

        $autopay_button = id(new PHUIButtonView())
          ->setColor($make_color)
          ->setIcon('fa-retweet')
          ->setText(pht('Start Autopay'));
      }

      $autopay_button
        ->setTag('a')
        ->setHref($autopay_uri)
        ->setWorkflow(true)
        ->setDisabled(!$can_edit);

      $rows[] = array(
        $method->getID(),
        phutil_tag(
          'a',
          array(
            'href' => $method->getURI(),
          ),
          $method->getFullDisplayName()),
        $method->getDisplayExpires(),
        $autopay_button,
        $remove_button,
      );

      if ($is_autopay) {
        $rowc[] = 'highlighted';
      } else {
        $rowc[] = null;
      }
    }

    $method_table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('ID'),
          pht('Payment Method'),
          pht('Expires'),
          null,
          null,
        ))
      ->setRowClasses($rowc)
      ->setColumnClasses(
        array(
          null,
          'pri wide',
          null,
          'right',
          null,
        ));

    if (!$autopay_method) {
      $method_table->setNotice(
        array(
          id(new PHUIIconView())->setIcon('fa-warning yellow'),
          ' ',
          pht('Autopay is not currently configured for this subscription.'),
        ));
    } else {
      $method_table->setNotice(
        array(
          id(new PHUIIconView())->setIcon('fa-check green'),
          ' ',
          pht(
            'Autopay is configured using %s.',
            phutil_tag(
              'a',
              array(
                'href' => $autopay_method->getURI(),
              ),
              $autopay_method->getFullDisplayName())),
        ));
    }

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($method_table);
  }

}
