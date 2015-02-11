<?php

abstract class PhabricatorPasteController extends PhabricatorController {

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    if ($for_app) {
      $nav->addFilter('create', pht('Create Paste'));
    }

    id(new PhabricatorPasteSearchEngine())
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

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Paste'))
        ->setHref($this->getApplicationURI('create/'))
        ->setIcon('fa-plus-square'));

    return $crumbs;
  }

  public function buildSourceCodeView(
    PhabricatorPaste $paste,
    $max_lines = null,
    $highlights = array()) {

    $lines = phutil_split_lines($paste->getContent());

    return id(new PhabricatorSourceCodeView())
      ->setLimit($max_lines)
      ->setLines($lines)
      ->setHighlights($highlights)
      ->setURI(new PhutilURI($paste->getURI()));
  }

}
