<?php

abstract class PhortuneMerchantController
  extends PhortuneController {

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Merchants'),
      $this->getApplicationURI('merchant/'));
    return $crumbs;
  }
}
