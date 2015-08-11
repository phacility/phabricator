<?php

final class PhortuneProductViewController extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $product = id(new PhortuneProductQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$product) {
      return new Aphront404Response();
    }

    $title = pht('Product: %s', $product->getProductName());

    $header = id(new PHUIHeaderView())
      ->setHeader($product->getProductName());

    $edit_uri = $this->getApplicationURI('product/edit/'.$product->getID().'/');

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObjectURI($request->getRequestURI());

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Products'),
      $this->getApplicationURI('product/'));
    $crumbs->addTextCrumb(
      pht('#%d', $product->getID()),
      $request->getRequestURI());

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
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
