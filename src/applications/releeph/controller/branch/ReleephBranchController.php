<?php

abstract class ReleephBranchController extends ReleephController {

  private $branch;

  public function setBranch($branch) {
    $this->branch = $branch;
    return $this;
  }

  public function getBranch() {
    return $this->branch;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $branch = $this->getBranch();
    if ($branch) {
      $product = $branch->getProduct();

      $crumbs->addTextCrumb(
        $product->getName(),
        $this->getProductViewURI($product));

      $crumbs->addTextCrumb(
        $branch->getName(),
        $this->getBranchViewURI($branch));
    }

    return $crumbs;
  }

}
