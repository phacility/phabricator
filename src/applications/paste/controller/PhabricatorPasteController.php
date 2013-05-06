<?php

abstract class PhabricatorPasteController extends PhabricatorController {

  public function buildSideNavView($filter = null, $for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI('filter/')));

    if ($for_app) {
      $nav->addFilter('', pht('Create Paste'),
        $this->getApplicationURI('/create/'));
    }

    $nav->addLabel(pht('Filters'));
    $nav->addFilter('all', pht('All Pastes'));
    if ($user->isLoggedIn()) {
      $nav->addFilter('my', pht('My Pastes'));
    }
    $nav->addFilter('advanced', pht('Advanced Search'));

    $nav->selectFilter($filter, 'all');

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(null, true)->getMenu();
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PhabricatorMenuItemView())
        ->setName(pht('Create Paste'))
        ->setHref($this->getApplicationURI('create/'))
        ->setIcon('create'));

    return $crumbs;
  }

  public function buildSourceCodeView(
    PhabricatorPaste $paste,
    $max_lines = null) {

    $lines = phutil_split_lines($paste->getContent());

    return id(new PhabricatorSourceCodeView())
      ->setLimit($max_lines)
      ->setLines($lines);
  }

}
