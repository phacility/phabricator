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
      ->setHeader($product->getProductName())
      ->setHeaderIcon('fa-gift');

    $edit_uri = $this->getApplicationURI('product/edit/'.$product->getID().'/');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Products'),
      $this->getApplicationURI('product/'));
    $crumbs->addTextCrumb(
      pht('#%d', $product->getID()),
      $request->getRequestURI());
    $crumbs->setBorder(true);

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->addProperty(
        pht('Price'),
        $product->getPriceAsCurrency()->formatForDisplay());

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addPropertyList($properties);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $object_box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
