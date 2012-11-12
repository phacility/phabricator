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
    $package_views = array(
      array('name' => 'Owned',
            'key'  => 'view/owned'),
      array('name' => 'All',
            'key'  => 'view/all'),
    );

    $package_views =
      array_merge($this->getExtraPackageViews(),
                  $package_views);

    $base_uri = new PhutilURI('/owners/');
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseUri($base_uri);

    $nav->addLabel('Packages');
    $nav->addFilters($package_views);

    $filter = $this->getSideNavFilter();
    $nav->selectFilter($filter, 'view/owned');

    return $nav;
  }

  protected function getExtraPackageViews() {
    return array();
  }

}
