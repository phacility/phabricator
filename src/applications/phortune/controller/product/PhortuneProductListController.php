<?php

final class PhortuneProductListController extends PhabricatorController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $pager = new AphrontCursorPagerView();
    $pager->readFromRequest($request);

    $query = id(new PhortuneProductQuery())
      ->setViewer($viewer);

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
    $crumbs->setBorder(true);

    $product_list = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setNoDataString(pht('No products.'));

    foreach ($products as $product) {
      $view_uri = $this->getApplicationURI(
        'product/view/'.$product->getID().'/');

      $price = $product->getPriceAsCurrency();

      $item = id(new PHUIObjectItemView())
        ->setObjectName($product->getID())
        ->setHeader($product->getProductName())
        ->setHref($view_uri)
        ->addAttribute($price->formatForDisplay())
        ->setImageIcon('fa-gift');

      $product_list->addItem($item);
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Products'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($product_list);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Products'))
      ->setHeaderIcon('fa-gift');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $box,
        $pager,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
