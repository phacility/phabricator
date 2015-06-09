<?php

final class PhortuneProductListController extends PhabricatorController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $pager = new AphrontCursorPagerView();
    $pager->readFromRequest($request);

    $query = id(new PhortuneProductQuery())
      ->setViewer($user);

    $products = $query->executeWithCursorPager($pager);

    $title = pht('Product List');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Products'),
      $this->getApplicationURI('product/'));
    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Product'))
        ->setHref($this->getApplicationURI('product/edit/'))
        ->setIcon('fa-plus-square'));

    $product_list = id(new PHUIObjectItemListView())
      ->setUser($user)
      ->setNoDataString(pht('No products.'));

    foreach ($products as $product) {
      $view_uri = $this->getApplicationURI(
        'product/view/'.$product->getID().'/');

      $price = $product->getPriceAsCurrency();

      $item = id(new PHUIObjectItemView())
        ->setObjectName($product->getID())
        ->setHeader($product->getProductName())
        ->setHref($view_uri)
        ->addAttribute($price->formatForDisplay());

      $product_list->addItem($item);
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $product_list,
        $pager,
      ),
      array(
        'title' => $title,
      ));
  }

}
