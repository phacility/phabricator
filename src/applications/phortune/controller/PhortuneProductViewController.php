<?php

final class PhortuneProductViewController extends PhortuneController {

  private $productID;

  public function willProcessRequest(array $data) {
    $this->productID = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $product = id(new PhortuneProductQuery())
      ->setViewer($user)
      ->withIDs(array($this->productID))
      ->executeOne();
    if (!$product) {
      return new Aphront404Response();
    }

    $title = pht('Product: %s', $product->getProductName());

    $header = id(new PHUIHeaderView())
      ->setHeader($product->getProductName());

    $edit_uri = $this->getApplicationURI('product/edit/'.$product->getID().'/');

    $actions = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObjectURI($request->getRequestURI());

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Products'),
      $this->getApplicationURI('product/'));
    $crumbs->addTextCrumb(
      pht('#%d', $product->getID()),
      $request->getRequestURI());

    $properties = id(new PHUIPropertyListView())
      ->setUser($user)
      ->setActionList($actions)
      ->addProperty(
        pht('Price'),
        $product->getPriceAsCurrency()->formatForDisplay());

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
