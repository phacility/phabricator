<?php

abstract class PonderController extends PhabricatorController {

  protected function buildSideNavView() {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new PonderQuestionSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs
      ->addAction(
        id(new PHUIListItemView())
          ->setName(pht('Create Question'))
          ->setHref('/ponder/question/edit/')
          ->setIcon('create'));

    return $crumbs;
  }

}
