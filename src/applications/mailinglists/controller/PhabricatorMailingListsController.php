<?php

abstract class PhabricatorMailingListsController extends PhabricatorController {

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    if ($for_app) {
      $nav->addFilter('edit', pht('Create List'));
    }

    id(new PhabricatorMailingListSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $can_manage = $this->hasApplicationCapability(
      PhabricatorMailingListsManageCapability::CAPABILITY);

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create List'))
        ->setHref($this->getApplicationURI('edit/'))
        ->setIcon('fa-plus-square')
        ->setDisabled(!$can_manage)
        ->setWorkflow(!$can_manage));

    return $crumbs;
  }

}
