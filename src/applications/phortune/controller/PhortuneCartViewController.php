<?php

final class PhortuneCartViewController
  extends PhortuneCartController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $cart = id(new PhortuneCartQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->needPurchases(true)
      ->executeOne();
    if (!$cart) {
      return new Aphront404Response();
    }

    $can_admin = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $cart->getMerchant(),
      PhabricatorPolicyCapability::CAN_EDIT);

    $cart_table = $this->buildCartContentTable($cart);

    $properties = $this->buildPropertyListView($cart);
    $actions = $this->buildActionListView($cart, $can_admin);
    $properties->setActionList($actions);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader(pht('Order Detail'))
      ->setPolicyObject($cart);

    $cart_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($properties)
      ->appendChild($cart_table);

    $charges = id(new PhortuneChargeQuery())
      ->setViewer($viewer)
      ->withCartPHIDs(array($cart->getPHID()))
      ->needCarts(true)
      ->execute();

    $charges_table = $this->buildChargesTable($charges, false);

    $account = $cart->getAccount();

    $crumbs = $this->buildApplicationCrumbs();
    $this->addAccountCrumb($crumbs, $cart->getAccount());
    $crumbs->addTextCrumb(pht('Cart %d', $cart->getID()));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $cart_box,
        $charges_table,
      ),
      array(
        'title' => pht('Cart'),
      ));

  }

  private function buildPropertyListView(PhortuneCart $cart) {

    $viewer = $this->getRequest()->getUser();

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

    return $view;
  }

  private function buildActionListView(PhortuneCart $cart, $can_admin) {
    $viewer = $this->getRequest()->getUser();
    $id = $cart->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $cart,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($cart);

    $can_cancel = ($can_edit && $cart->canCancelOrder());

    $cancel_uri = $this->getApplicationURI("cart/{$id}/cancel/");
    $refund_uri = $this->getApplicationURI("cart/{$id}/refund/");

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Cancel Order'))
        ->setIcon('fa-times')
        ->setDisabled(!$can_cancel)
        ->setWorkflow(true)
        ->setHref($cancel_uri));

    if ($can_admin) {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Refund Order'))
          ->setIcon('fa-reply')
          ->setWorkflow(true)
          ->setHref($refund_uri));
    }

    return $view;
  }

}
