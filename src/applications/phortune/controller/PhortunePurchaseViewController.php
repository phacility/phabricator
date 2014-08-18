<?php

final class PhortunePurchaseViewController extends PhortuneController {

  private $purchaseID;

  public function willProcessRequest(array $data) {
    $this->purchaseID = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $account = $this->loadActiveAccount($viewer);

    $purchase = id(new PhortunePurchaseQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->purchaseID))
      ->executeOne();
    if (!$purchase) {
      return new Aphront404Response();
    }
    $cart = $purchase->getCart();

    $title = pht('Purchase: %s', $purchase->getFullDisplayName());

    $header = id(new PHUIHeaderView())
      ->setHeader($purchase->getFullDisplayName());

    $crumbs = $this->buildApplicationCrumbs();
    $this->addAccountCrumb($crumbs, $account);
    $crumbs->addTextCrumb(
      pht('Cart %d', $cart->getID()),
      $this->getApplicationURI('cart/'.$cart->getID().'/'));
    $crumbs->addTextCrumb(
      pht('Purchase %d', $purchase->getID()),
      $this->getApplicationURI('purchase/'.$purchase->getID().'/'));

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->addProperty(pht('Status'), $purchase->getStatus());

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
      ),
      array(
        'title' => $title,
      ));
  }

}
