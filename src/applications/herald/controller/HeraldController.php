<?php

abstract class HeraldController extends PhabricatorController {

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Herald Rule'))
        ->setHref($this->getApplicationURI('create/'))
        ->setIcon('fa-plus-square'));

    return $crumbs;
  }

  public function buildSideNavView() {
    $viewer = $this->getViewer();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new HeraldRuleSearchEngine())
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());

    $nav->addLabel(pht('Utilities'))
        ->addFilter('test', pht('Test Console'))
        ->addFilter('transcript', pht('Transcripts'));

    $nav->selectFilter(null);

    return $nav;
  }

}
