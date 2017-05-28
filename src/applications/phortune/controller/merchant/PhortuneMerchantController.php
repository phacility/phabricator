<?php

abstract class PhortuneMerchantController
  extends PhortuneController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Merchants'),
      $this->getApplicationURI('merchant/'));
    return $crumbs;
  }
}
