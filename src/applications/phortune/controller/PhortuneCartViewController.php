<?php

final class PhortuneCartViewController
  extends PhortuneCartController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $authority = $this->loadMerchantAuthority();
    require_celerity_resource('phortune-css');

    $query = id(new PhortuneCartQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needPurchases(true);

    if ($authority) {
      $query->withMerchantPHIDs(array($authority->getPHID()));
    }

    $cart = $query->executeOne();
    if (!$cart) {
      return new Aphront404Response();
    }

    $cart_table = $this->buildCartContentTable($cart);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $cart,
      PhabricatorPolicyCapability::CAN_EDIT);

    $errors = array();
    $error_view = null;
    $resume_uri = null;
    switch ($cart->getStatus()) {
      case PhortuneCart::STATUS_READY:
        if ($authority && $cart->getIsInvoice()) {
          // We arrived here by following the ad-hoc invoice workflow, and
          // are acting with merchant authority.

          $checkout_uri = PhabricatorEnv::getURI($cart->getCheckoutURI());

          $invoice_message = array(
            pht(
              'Manual invoices do not automatically notify recipients yet. '.
              'Send the payer this checkout link:'),
            ' ',
            phutil_tag(
              'a',
              array(
                'href' => $checkout_uri,
              ),
              $checkout_uri),
          );

          $error_view = id(new PHUIInfoView())
            ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
            ->setErrors(array($invoice_message));
        }
        break;
      case PhortuneCart::STATUS_PURCHASING:
        if ($can_edit) {
          $resume_uri = $cart->getMetadataValue('provider.checkoutURI');
          if ($resume_uri) {
            $errors[] = pht(
              'The checkout process has been started, but not yet completed. '.
              'You can continue checking out by clicking %s, or cancel the '.
              'order, or contact the merchant for assistance.',
              phutil_tag('strong', array(), pht('Continue Checkout')));
          } else {
            $errors[] = pht(
              'The checkout process has been started, but an error occurred. '.
              'You can cancel the order or contact the merchant for '.
              'assistance.');
          }
        }
        break;
      case PhortuneCart::STATUS_CHARGED:
        if ($can_edit) {
          $errors[] = pht(
            'You have been charged, but processing could not be completed. '.
            'You can cancel your order, or contact the merchant for '.
            'assistance.');
        }
        break;
      case PhortuneCart::STATUS_HOLD:
        if ($can_edit) {
          $errors[] = pht(
            'Payment for this order is on hold. You can click %s to check '.
            'for updates, cancel the order, or contact the merchant for '.
            'assistance.',
            phutil_tag('strong', array(), pht('Update Status')));
        }
        break;
      case PhortuneCart::STATUS_REVIEW:
        if ($authority) {
          $errors[] = pht(
            'This order has been flagged for manual review. Review the order '.
            'and choose %s to accept it or %s to reject it.',
            phutil_tag('strong', array(), pht('Accept Order')),
            phutil_tag('strong', array(), pht('Refund Order')));
        } else if ($can_edit) {
          $errors[] = pht(
            'This order requires manual processing and will complete once '.
            'the merchant accepts it.');
        }
        break;
      case PhortuneCart::STATUS_PURCHASED:
        $error_view = id(new PHUIInfoView())
          ->setSeverity(PHUIInfoView::SEVERITY_SUCCESS)
          ->appendChild(pht('This purchase has been completed.'));
        break;
    }

    if ($errors) {
      $error_view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->appendChild($errors);
    }

    $details = $this->buildDetailsView($cart);
    $curtain = $this->buildCurtainView(
      $cart,
      $can_edit,
      $authority,
      $resume_uri);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader(pht('Order Detail'))
      ->setHeaderIcon('fa-shopping-cart');

    if ($cart->getStatus() == PhortuneCart::STATUS_PURCHASED) {
      $done_uri = $cart->getDoneURI();
      if ($done_uri) {
        $header->addActionLink(
          id(new PHUIButtonView())
            ->setTag('a')
            ->setHref($done_uri)
            ->setIcon('fa-check-square green')
            ->setText($cart->getDoneActionName()));
      }
    }

    $cart_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Cart Items'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($cart_table);

    $description = $this->renderCartDescription($cart);

    $charges = id(new PhortuneChargeQuery())
      ->setViewer($viewer)
      ->withCartPHIDs(array($cart->getPHID()))
      ->needCarts(true)
      ->execute();

    $phids = array();
    foreach ($charges as $charge) {
      $phids[] = $charge->getProviderPHID();
      $phids[] = $charge->getCartPHID();
      $phids[] = $charge->getMerchantPHID();
      $phids[] = $charge->getPaymentMethodPHID();
    }
    $handles = $this->loadViewerHandles($phids);

    $charges_table = id(new PhortuneChargeTableView())
      ->setUser($viewer)
      ->setHandles($handles)
      ->setCharges($charges)
      ->setShowOrder(false);

    $charges = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Charges'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($charges_table);

    $account = $cart->getAccount();

    $crumbs = $this->buildApplicationCrumbs();
    if ($authority) {
      $this->addMerchantCrumb($crumbs, $authority);
    } else {
      $this->addAccountCrumb($crumbs, $cart->getAccount());
    }
    $crumbs->addTextCrumb(pht('Cart %d', $cart->getID()));
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $cart,
      new PhortuneCartTransactionQuery());
    $timeline
     ->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $error_view,
        $details,
        $cart_box,
        $description,
        $charges,
        $timeline,
      ));

    return $this->newPage()
      ->setTitle(pht('Cart %d', $cart->getID()))
      ->setCrumbs($crumbs)
      ->addClass('phortune-cart-page')
      ->appendChild($view);

  }

  private function buildDetailsView(PhortuneCart $cart) {

    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($cart);

    $handles = $this->loadViewerHandles(
      array(
        $cart->getAccountPHID(),
        $cart->getAuthorPHID(),
        $cart->getMerchantPHID(),
      ));

    $view->addProperty(
      pht('Order Name'),
      $cart->getName());
    $view->addProperty(
      pht('Account'),
      $handles[$cart->getAccountPHID()]->renderLink());
    $view->addProperty(
      pht('Authorized By'),
      $handles[$cart->getAuthorPHID()]->renderLink());
    $view->addProperty(
      pht('Merchant'),
      $handles[$cart->getMerchantPHID()]->renderLink());
    $view->addProperty(
      pht('Status'),
      PhortuneCart::getNameForStatus($cart->getStatus()));
    $view->addProperty(
      pht('Updated'),
      phabricator_datetime($cart->getDateModified(), $viewer));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($view);
  }

  private function buildCurtainView(
    PhortuneCart $cart,
    $can_edit,
    $authority,
    $resume_uri) {

    $viewer = $this->getViewer();
    $id = $cart->getID();
    $curtain = $this->newCurtainView($cart);

    $can_cancel = ($can_edit && $cart->canCancelOrder());

    if ($authority) {
      $prefix = 'merchant/'.$authority->getID().'/';
    } else {
      $prefix = '';
    }

    $cancel_uri = $this->getApplicationURI("{$prefix}cart/{$id}/cancel/");
    $refund_uri = $this->getApplicationURI("{$prefix}cart/{$id}/refund/");
    $update_uri = $this->getApplicationURI("{$prefix}cart/{$id}/update/");
    $accept_uri = $this->getApplicationURI("{$prefix}cart/{$id}/accept/");
    $print_uri = $this->getApplicationURI("{$prefix}cart/{$id}/?__print__=1");

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Cancel Order'))
        ->setIcon('fa-times')
        ->setDisabled(!$can_cancel)
        ->setWorkflow(true)
        ->setHref($cancel_uri));

    if ($authority) {
      if ($cart->getStatus() == PhortuneCart::STATUS_REVIEW) {
        $curtain->addAction(
          id(new PhabricatorActionView())
            ->setName(pht('Accept Order'))
            ->setIcon('fa-check')
            ->setWorkflow(true)
            ->setHref($accept_uri));
      }

      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Refund Order'))
          ->setIcon('fa-reply')
          ->setWorkflow(true)
          ->setHref($refund_uri));
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Update Status'))
        ->setIcon('fa-refresh')
        ->setHref($update_uri));

    if ($can_edit && $resume_uri) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Continue Checkout'))
          ->setIcon('fa-shopping-cart')
          ->setHref($resume_uri));
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Printable Version'))
        ->setHref($print_uri)
        ->setOpenInNewWindow(true)
        ->setIcon('fa-print'));

    return $curtain;
  }

}
