<?php

abstract class PhabricatorPasteController extends PhabricatorController {

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    if ($for_app) {
      $nav->addFilter('create', pht('Create Paste'));
    }

    $nav->addLabel(pht('Queries'));

    $named_queries = id(new PhabricatorNamedQueryQuery())
      ->setViewer($user)
      ->withUserPHIDs(array($user->getPHID()))
      ->withEngineClassNames(array('PhabricatorPasteSearchEngine'))
      ->execute();

    foreach ($named_queries as $query) {
      $nav->addFilter('query/'.$query->getQueryKey(), $query->getQueryName());
    }

    $nav->addFilter('filter/all', pht('All Pastes'));
    if ($user->isLoggedIn()) {
      $nav->addFilter('filter/my', pht('My Pastes'));
    }
    $nav->addFilter('savedqueries', pht('Edit Queries...'));

    $nav->addLabel(pht('Search'));
    $nav->addFilter('filter/advanced', pht('Advanced Search'));

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
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
