<?php

final class PhortuneMerchantListController
  extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    return id(new PhortuneMerchantSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $can_create = $this->hasApplicationCapability(
      PhortuneMerchantCapability::CAPABILITY);

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Merchant'))
        ->setHref($this->getApplicationURI('merchant/edit/'))
        ->setIcon('fa-plus-square')
        ->setWorkflow(!$can_create)
        ->setDisabled(!$can_create));

    return $crumbs;
  }

}
