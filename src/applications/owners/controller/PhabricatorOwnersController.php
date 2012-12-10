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
