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
  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();

    $page->setApplicationName('Owners');
    $page->setBaseURI('/owners/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x98\x81");
    $nav = $this->renderSideNav();
    $nav->appendChild($view);
    $page->appendChild($nav);

    $filter = $nav->getSelectedFilter();
    switch ($filter) {
      case 'view/owned':
      case 'view/all':
        $crumbs = $this->buildApplicationCrumbs();

        if ($filter == 'view/owned') {
          $title = pht('Owned Packages');
        } else {
          $title = pht('All Packages');
        }

        $crumbs->addCrumb(
          id(new PhabricatorCrumbView())
            ->setName($title));

        $crumbs->addAction(
          id(new PhabricatorMenuItemView())
            ->setName(pht('Create Package'))
            ->setHref('/owners/new/')
            ->setIcon('create'));

        $nav->setCrumbs($crumbs);
        break;
    }

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  public function renderSideNav() {
    $nav = new AphrontSideNavFilterView();
    $base_uri = new PhutilURI('/owners/');
    $nav->setBaseURI($base_uri);

    $nav->addLabel('Packages');
    $this->getExtraPackageViews($nav);
    $nav->addFilter('view/owned', 'Owned');
    $nav->addFilter('view/all', 'All');

    $nav->selectFilter($this->getSideNavFilter(), 'view/owned');

    return $nav;
  }

  protected function getExtraPackageViews(AphrontSideNavFilterView $view) {
    return;
  }

}
