<?php

abstract class HeraldController extends PhabricatorController {

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
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

    $nav->addLabel(pht('Webhooks'))
      ->addFilter('webhook', pht('Webhooks'));

    $nav->selectFilter(null);

    return $nav;
  }

}
