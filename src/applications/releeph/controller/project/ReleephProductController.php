<?php

abstract class ReleephProductController extends ReleephController {

  private $product;

  protected function setProduct(ReleephProject $product) {
    $this->product = $product;
    return $this;
  }

  protected function getProduct() {
    return $this->product;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $product = $this->getProduct();
    if ($product) {
      $crumbs->addTextCrumb(
        $product->getName(),
        $this->getProductViewURI($product));
    }

    return $crumbs;
  }


}
