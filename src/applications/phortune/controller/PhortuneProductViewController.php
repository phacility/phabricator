<?php

final class PhortuneProductViewController extends PhabricatorController {

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

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Products'))
        ->setHref($this->getApplicationURI('product/')));
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('#%d', $product->getID()))
        ->setHref($request->getRequestURI()));

    $header = id(new PhabricatorHeaderView())
      ->setHeader($product->getProductName());

    $edit_uri = $this->getApplicationURI('product/edit/'.$product->getID().'/');

    $actions = id(new PhabricatorActionListView())
      ->setUser($user)
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Product'))
          ->setHref($edit_uri)
          ->setIcon('edit'));

    $properties = id(new PhabricatorPropertyListView())
      ->setUser($user)
      ->addProperty(pht('Type'), $product->getTypeName())
      ->addProperty(
        pht('Price'),
        PhortuneUtil::formatCurrency($product->getPriceInCents()));

    $xactions = id(new PhortuneProductTransactionQuery())
      ->setViewer($user)
      ->withObjectPHIDs(array($product->getPHID()))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);

    $xaction_view = id(new PhabricatorApplicationTransactionView())
      ->setUser($user)
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $actions,
        $properties,
        $xaction_view,
      ),
      array(
        'title' => $title,
        'device' => true,
        'dust' => true,
      ));
  }

}
