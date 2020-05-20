<?php

final class PhortuneCartViewController
  extends PhortuneCartController {

  protected function shouldRequireAccountAuthority() {
    return false;
  }

  protected function shouldRequireMerchantAuthority() {
    return false;
  }

  protected function handleCartRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $order = $this->getCart();
    $authority = $this->getMerchantAuthority();
    $can_edit = $this->hasAccountAuthority();

    $is_printable = ($request->getURIData('action') === 'print');

    $resume_uri = null;
    if ($order->getStatus() === PhortuneCart::STATUS_PURCHASING) {
      if ($can_edit) {
        $resume_uri = $order->getMetadataValue('provider.checkoutURI');
      }
    }

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($order->getName())
      ->setHeaderIcon('fa-shopping-bag');

    if ($order->getStatus() == PhortuneCart::STATUS_PURCHASED) {
      $done_uri = $order->getDoneURI();
      if ($done_uri) {
        $header->addActionLink(
          id(new PHUIButtonView())
            ->setTag('a')
            ->setHref($done_uri)
            ->setIcon('fa-check-square green')
            ->setText($order->getDoneActionName()));
      }
    }

    $order_view = id(new PhortuneOrderSummaryView())
      ->setViewer($viewer)
      ->setOrder($order)
      ->setResumeURI($resume_uri)
      ->setPrintable($is_printable);

    $crumbs = null;
    $curtain = null;

    $main = array();
    $tail = array();

    require_celerity_resource('phortune-invoice-css');

    if ($is_printable) {
      $body_class = 'phortune-invoice-view';

      $tail[] = $order_view;
    } else {
      $body_class = 'phortune-cart-page';

      $curtain = $this->buildCurtainView(
        $order,
        $can_edit,
        $authority,
        $resume_uri);

      $account = $order->getAccount();
      $crumbs = $this->buildApplicationCrumbs()
        ->addTextCrumb($account->getName(), $account->getURI())
        ->addTextCrumb(pht('Orders'), $account->getOrdersURI())
        ->addTextCrumb($order->getObjectName())
        ->setBorder(true);

      $timeline = $this->buildTransactionTimeline($order)
        ->setShouldTerminate(true);

      $main[] = $order_view;
      $main[] = $timeline;
    }

    $column_view = id(new PHUITwoColumnView())
      ->setMainColumn($main)
      ->setFooter($tail);

    if ($curtain) {
      $column_view->setCurtain($curtain);
    }

    $page = $this->newPage()
      ->addClass($body_class)
      ->setTitle(
        array(
          $order->getObjectName(),
          $order->getName(),
        ))
      ->appendChild($column_view);

    if ($crumbs) {
      $page->setCrumbs($crumbs);
    }

    return $page;
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
        ->setName(pht('Printable Version'))
        ->setHref($print_uri)
        ->setOpenInNewWindow(true)
        ->setIcon('fa-print'));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setType(PhabricatorActionView::TYPE_DIVIDER));

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
