<?php

abstract class PhabricatorOwnersController extends PhabricatorController {

  private $filter;

  private function getSideNavFilter() {
    return $this->filter;
  }
  protected function setSideNavFilter($filter) {
    $this->filter = $filter;
    return $this;
  }

  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $base_uri = new PhutilURI('/owners/');
    $nav->setBaseURI($base_uri);

    $nav->addLabel(pht('Packages'));
    $this->getExtraPackageViews($nav);
    $nav->addFilter('view/owned', pht('Owned'));
    $nav->addFilter('view/projects', pht('Projects'));
    $nav->addFilter('view/all', pht('All'));

    $nav->selectFilter($this->getSideNavFilter(), 'view/owned');

    $filter = $nav->getSelectedFilter();
    switch ($filter) {
      case 'view/owned':
        $title = pht('Owned Packages');
        break;
      case 'view/all':
        $title = pht('All Packages');
        break;
      case 'view/projects':
        $title = pht('Projects');
        break;
      case 'new':
        $title = pht('New Package');
        break;
      default:
        $title = pht('Package');
        break;
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($title));

    $crumbs->addAction(
      id(new PhabricatorMenuItemView())
        ->setName(pht('Create Package'))
        ->setHref('/owners/new/')
        ->setIcon('create'));

    $nav->setCrumbs($crumbs);

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

  protected function getExtraPackageViews(AphrontSideNavFilterView $view) {
    return;
  }

}
