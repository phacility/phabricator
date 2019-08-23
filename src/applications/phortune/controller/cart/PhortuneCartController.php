<?php

abstract class PhortuneCartController
  extends PhortuneController {

  private $cart;
  private $merchantAuthority;

  abstract protected function shouldRequireAccountAuthority();
  abstract protected function shouldRequireMerchantAuthority();
  abstract protected function handleCartRequest(AphrontRequest $request);

  final public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    if ($this->shouldRequireAccountAuthority()) {
      $capabilities = array(
        PhabricatorPolicyCapability::CAN_VIEW,
        PhabricatorPolicyCapability::CAN_EDIT,
      );
    } else {
      $capabilities = array(
        PhabricatorPolicyCapability::CAN_VIEW,
      );
    }

    $cart = id(new PhortuneCartQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->needPurchases(true)
      ->requireCapabilities($capabilities)
      ->executeOne();
    if (!$cart) {
      return new Aphront404Response();
    }

    if ($this->shouldRequireMerchantAuthority()) {
      PhabricatorPolicyFilter::requireCapability(
        $viewer,
        $cart->getMerchant(),
        PhabricatorPolicyCapability::CAN_EDIT);
    }

    $this->cart = $cart;

    $can_edit = PhortuneMerchantQuery::canViewersEditMerchants(
      array($viewer->getPHID()),
      array($cart->getMerchantPHID()));
    if ($can_edit) {
      $this->merchantAuthority = $cart->getMerchant();
    } else {
      $this->merchantAuthority = null;
    }

    return $this->handleCartRequest($request);
  }

  final protected function getCart() {
    return $this->cart;
  }

  final protected function getMerchantAuthority() {
    return $this->merchantAuthority;
  }

  final protected function hasMerchantAuthority() {
    return (bool)$this->merchantAuthority;
  }

  final protected function hasAccountAuthority() {
    return (bool)PhabricatorPolicyFilter::hasCapability(
      $this->getViewer(),
      $this->getCart(),
      PhabricatorPolicyCapability::CAN_EDIT);
  }

  protected function buildCartContentTable(PhortuneCart $cart) {

    $rows = array();
    foreach ($cart->getPurchases() as $purchase) {
      $rows[] = array(
        $purchase->getFullDisplayName(),
        $purchase->getBasePriceAsCurrency()->formatForDisplay(),
        $purchase->getQuantity(),
        $purchase->getTotalPriceAsCurrency()->formatForDisplay(),
      );
    }

    $rows[] = array(
      phutil_tag('strong', array(), pht('Total')),
      '',
      '',
      phutil_tag('strong', array(),
        $cart->getTotalPriceAsCurrency()->formatForDisplay()),
    );

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Item'),
        pht('Price'),
        pht('Qty.'),
        pht('Total'),
      ));
    $table->setColumnClasses(
      array(
        'wide',
        'right',
        'right',
        'right',
      ));

    return $table;
  }

  protected function renderCartDescription(PhortuneCart $cart) {
    $description = $cart->getDescription();
    if (!strlen($description)) {
      return null;
    }

    $output = new PHUIRemarkupView($this->getViewer(), $description);

    $box = id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_LARGE)
      ->appendChild($output);

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Description'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($box);
  }

}
