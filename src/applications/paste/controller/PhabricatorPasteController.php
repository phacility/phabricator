<?php

abstract class PhabricatorPasteController extends PhabricatorController {

  public function buildSideNavView($filter = null, $for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI('filter/')));

    if ($for_app) {
      $nav->addFilter('', 'Create Paste', $this->getApplicationURI('/create/'));
    }

    $nav->addLabel('Filters');
    $nav->addFilter('all', 'All Pastes');
    if ($user->isLoggedIn()) {
      $nav->addFilter('my', 'My Pastes');
    }

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
    PhabricatorFile $file,
    $max_lines = null) {

    $language = $paste->getLanguage();
    $source = $file->loadFileData();

    if (empty($language)) {
      $source = PhabricatorSyntaxHighlighter::highlightWithFilename(
        $paste->getTitle(),
        $source);
    } else {
      $source = PhabricatorSyntaxHighlighter::highlightWithLanguage(
        $language,
        $source);
    }

    $lines = explode("\n", $source);

    if ($max_lines) {
      $lines = array_slice($lines, 0, $max_lines);
    }

    return id(new PhabricatorSourceCodeView())
      ->setLines($lines);
  }
}
