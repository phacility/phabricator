<?php

final class PhortuneCartViewController
  extends PhortuneCartController {

  private $action = null;

  protected function shouldRequireAccountAuthority() {
    return false;
  }

  protected function shouldRequireMerchantAuthority() {
    return false;
  }

  protected function handleCartRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $cart = $this->getCart();
    $authority = $this->getMerchantAuthority();
    $can_edit = $this->hasAccountAuthority();

    $this->action = $request->getURIData('action');

    $cart_table = $this->buildCartContentTable($cart);

    $errors = array();
    $error_view = null;
    $resume_uri = null;
    switch ($cart->getStatus()) {
      case PhortuneCart::STATUS_READY:
        if ($cart->getIsInvoice()) {
          $error_view = id(new PHUIInfoView())
            ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
            ->appendChild(pht('This invoice is ready for payment.'));
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
      ->setHeader($cart->getName())
      ->setHeaderIcon('fa-shopping-bag');

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

    $charges_table = id(new PhortuneChargeTableView())
      ->setUser($viewer)
      ->setCharges($charges)
      ->setShowOrder(false);

    $charges = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Charges'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($charges_table);

    $account = $cart->getAccount();

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($account->getName(), $account->getURI())
      ->addTextCrumb(pht('Orders'), $account->getOrdersURI())
      ->addTextCrumb(pht('Cart %d', $cart->getID()))
      ->setBorder(true);

    require_celerity_resource('phortune-css');

    if (!$this->action) {
      $class = 'phortune-cart-page';
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

    } else {
      $class = 'phortune-invoice-view';
      $crumbs = null;
      $merchant_phid = $cart->getMerchantPHID();
      $buyer_phid = $cart->getAuthorPHID();
      $merchant = id(new PhortuneMerchantQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($merchant_phid))
        ->needProfileImage(true)
        ->executeOne();
      $buyer = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($buyer_phid))
        ->needProfileImage(true)
        ->executeOne();

      $merchant_contact = new PHUIRemarkupView(
        $viewer,
        $merchant->getContactInfo());

      $account_name = $account->getBillingName();
      if (!strlen($account_name)) {
        $account_name = $buyer->getRealName();
      }

      $account_contact = $account->getBillingAddress();
      if (strlen($account_contact)) {
        $account_contact = new PHUIRemarkupView(
          $viewer,
          $account_contact);
      }

      $view = id(new PhortuneInvoiceView())
        ->setMerchantName($merchant->getName())
        ->setMerchantLogo($merchant->getProfileImageURI())
        ->setMerchantContact($merchant_contact)
        ->setMerchantFooter($merchant->getInvoiceFooter())
        ->setAccountName($account_name)
        ->setAccountContact($account_contact)
        ->setStatus($error_view)
        ->setContent(
          array(
            $details,
            $cart_box,
            $charges,
          ));
    }

    $page = $this->newPage()
      ->setTitle(pht('Cart %d', $cart->getID()))
      ->addClass($class)
      ->appendChild($view);

    if ($crumbs) {
      $page->setCrumbs($crumbs);
    }

    return $page;
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

    if ($this->action == 'print') {
      $view->addProperty(pht('Order Name'), $cart->getName());
    }

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
    $status = $cart->getStatus();

    $is_ready = ($status === PhortuneCart::STATUS_READY);

    $can_cancel = ($can_edit && $cart->canCancelOrder());
    $can_checkout = ($can_edit && $is_ready);
    $can_accept = ($status === PhortuneCart::STATUS_REVIEW);
    $can_refund = ($authority && $cart->canRefundOrder());
    $can_void = ($authority && $cart->canVoidOrder());

    $cancel_uri = $this->getApplicationURI("cart/{$id}/cancel/");
    $refund_uri = $this->getApplicationURI("cart/{$id}/refund/");
    $update_uri = $this->getApplicationURI("cart/{$id}/update/");
    $accept_uri = $this->getApplicationURI("cart/{$id}/accept/");
    $print_uri = $this->getApplicationURI("cart/{$id}/print/");
    $checkout_uri = $cart->getCheckoutURI();
    $void_uri = $this->getApplicationURI("cart/{$id}/void/");

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Pay Now'))
        ->setIcon('fa-credit-card')
        ->setDisabled(!$can_checkout)
        ->setWorkflow(!$can_checkout)
        ->setHref($checkout_uri));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Cancel Order'))
        ->setIcon('fa-times')
        ->setDisabled(!$can_cancel)
        ->setWorkflow(true)
        ->setHref($cancel_uri));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Update Status'))
        ->setIcon('fa-refresh')
        ->setHref($update_uri));

    if ($can_edit && $resume_uri) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Continue Checkout'))
          ->setIcon('fa-shopping-bag')
          ->setHref($resume_uri));
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Printable Version'))
        ->setHref($print_uri)
        ->setOpenInNewWindow(true)
        ->setIcon('fa-print'));

    if ($authority) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setType(PhabricatorActionView::TYPE_DIVIDER));

      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Accept Order'))
          ->setIcon('fa-check')
          ->setWorkflow(true)
          ->setDisabled(!$can_accept)
          ->setHref($accept_uri));

      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Refund Order'))
          ->setIcon('fa-reply')
          ->setWorkflow(true)
          ->setDisabled(!$can_refund)
          ->setHref($refund_uri));

      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Void Invoice'))
          ->setIcon('fa-times')
          ->setWorkflow(true)
          ->setDisabled(!$can_void)
          ->setHref($void_uri));
    }

    return $curtain;
  }

}
