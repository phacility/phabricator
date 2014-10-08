<?php

abstract class PhabricatorMacroController extends PhabricatorController {

  protected function buildSideNavView($for_app = false) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    if ($for_app) {
      $nav->addLabel(pht('Create'));
      $nav->addFilter('',
        pht('Create Macro'),
        $this->getApplicationURI('/create/'));
    }

    id(new PhabricatorMacroSearchEngine())
      ->setViewer($this->getRequest()->getUser())
      ->addNavigationItems($nav->getMenu());

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView($for_app = true)->getMenu();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $can_manage = $this->hasApplicationCapability(
      PhabricatorMacroManageCapability::CAPABILITY);

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Macro'))
        ->setHref($this->getApplicationURI('/create/'))
        ->setIcon('fa-plus-square')
        ->setDisabled(!$can_manage)
        ->setWorkflow(!$can_manage));

    return $crumbs;
  }

}
