<?php

abstract class PhabricatorAuthProviderConfigController
  extends PhabricatorAuthController {

  public function shouldRequireAdmin() {
    return true;
  }

  protected function buildSideNavView($for_app = false) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    if ($for_app) {
      $nav->addLabel(pht('Create'));
      $nav->addFilter('',
        pht('Add Authentication Provider'),
        $this->getApplicationURI('/config/new/'));
    }
    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView($for_app = true)->getMenu();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Add Authentication Provider'))
        ->setHref($this->getApplicationURI('/config/new/'))
        ->setIcon('fa-plus-square'));

    return $crumbs;
  }

}
