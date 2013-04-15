<?php

abstract class DifferentialController extends PhabricatorController {

  protected function allowsAnonymousAccess() {
    return PhabricatorEnv::getEnvConfig('differential.anonymous-access');
  }

  public function buildStandardPageResponse($view, array $data) {

    require_celerity_resource('differential-core-view-css');

    $page = $this->buildStandardPageView();
    $page->setApplicationName(pht('Differential'));
    $page->setBaseURI('/differential/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x9A\x99");
    $page->appendChild($view);
    $page->setSearchDefaultScope(PhabricatorSearchScope::SCOPE_OPEN_REVISIONS);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PhabricatorMenuItemView())
        ->setHref($this->getApplicationURI('/diff/create/'))
        ->setName(pht('Create Diff'))
        ->setIcon('create'));

    return $crumbs;
  }

  public function buildSideNav($filter = null,
    $for_app = false, $username = null) {

    $viewer_is_anonymous = !$this->getRequest()->getUser()->isLoggedIn();

    $uri = id(new PhutilURI('/differential/filter/'))
      ->setQueryParams($this->getRequest()->getRequestURI()->getQueryParams());
    $filters = $this->getFilters();
    $filter = $this->selectFilter($filters, $filter, $viewer_is_anonymous);

    $side_nav = new AphrontSideNavFilterView();
    $side_nav->setBaseURI($uri);
    foreach ($filters as $filter) {
      list($filter_name, $display_name) = $filter;
      if ($filter_name) {
        $side_nav->addFilter($filter_name.'/'.$username, $display_name);
      } else {
        $side_nav->addLabel($display_name);
      }
    }

    return $side_nav;
  }

  protected function getFilters() {
    return array(
      array(null, pht('User Revisions')),
      array('active', pht('Active')),
      array('revisions', pht('Revisions')),
      array('reviews', pht('Reviews')),
      array('subscribed', pht('Subscribed')),
      array('drafts', pht('Draft Reviews')),
      array(null, pht('All Revisions')),
      array('all', pht('All')),
    );
  }

  protected function selectFilter(
    array $filters,
    $requested_filter,
    $viewer_is_anonymous) {

    $default_filter = ($viewer_is_anonymous ? 'all' : 'active');

    // If the user requested a filter, make sure it actually exists.
    if ($requested_filter) {
      foreach ($filters as $filter) {
        if ($filter[0] === $requested_filter) {
          return $requested_filter;
        }
      }
    }

    // If not, return the default filter.
    return $default_filter;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNav(null, true)->getMenu();
  }

}
