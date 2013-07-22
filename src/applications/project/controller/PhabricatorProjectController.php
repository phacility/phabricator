<?php

abstract class PhabricatorProjectController extends PhabricatorController {

  public function buildSideNavView($filter = null, $for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav
      ->setBaseURI(new PhutilURI('/project/filter/'))
      ->addLabel(pht('User'))
      ->addFilter('active', pht('Active'))
      ->addLabel(pht('All'))
      ->addFilter('all', pht('All Projects'))
      ->addFilter('allactive', pht('Active Projects'))
      ->selectFilter($filter, 'active');

    if ($for_app) {
      $nav->addFilter('create/', pht('Create Project'));
    }

    return $nav;
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Project'))
        ->setHref($this->getApplicationURI('create/'))
        ->setIcon('create'));

    return $crumbs;
  }

}
