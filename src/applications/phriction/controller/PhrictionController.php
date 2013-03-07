<?php

/**
 * @group phriction
 */
abstract class PhrictionController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();

    $page->setApplicationName(pht('Phriction'));
    $page->setBaseURI('/w/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x9A\xA1");

    $page->appendChild($view);
    $page->setSearchDefaultScope(PhabricatorSearchScope::SCOPE_WIKI);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  public function buildSideNavView($filter = null, $for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/phriction/list/'));

    if ($for_app) {
      $nav->addFilter('', pht('Root Document'), '/w/');
      $nav->addFilter('', pht('Create Document'), '/phriction/new');
    }

    $nav->addLabel(pht('Filters'));
    $nav->addFilter('active', pht('Active Documents'));
    $nav->addFilter('all', pht('All Documents'));
    $nav->addFilter('updates', pht('Recently Updated'));

    $nav->selectFilter($filter, 'active');

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(null, true)->getMenu();
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PhabricatorMenuItemView())
        ->setName(pht('Create Document'))
        ->setHref('/phriction/new/?slug='.$this->getDocumentSlug())
        ->setWorkflow(true)
        ->setIcon('create'));

    return $crumbs;
  }

  public function renderBreadcrumbs($slug) {
    $ancestor_handles = array();
    $ancestral_slugs = PhabricatorSlug::getAncestry($slug);
    $ancestral_slugs[] = $slug;
    if ($ancestral_slugs) {
      $empty_slugs = array_fill_keys($ancestral_slugs, null);
      $ancestors = id(new PhrictionDocument())->loadAllWhere(
        'slug IN (%Ls)',
        $ancestral_slugs);
      $ancestors = mpull($ancestors, null, 'getSlug');

      $ancestor_phids = mpull($ancestors, 'getPHID');
      $handles = array();
      if ($ancestor_phids) {
        $handles = $this->loadViewerHandles($ancestor_phids);
      }

      $ancestor_handles = array();
      foreach ($ancestral_slugs as $slug) {
        if (isset($ancestors[$slug])) {
          $ancestor_handles[] = $handles[$ancestors[$slug]->getPHID()];
        } else {
          $handle = new PhabricatorObjectHandle();
          $handle->setName(PhabricatorSlug::getDefaultTitle($slug));
          $handle->setURI(PhrictionDocument::getSlugURI($slug));
          $ancestor_handles[] = $handle;
        }
      }
    }

    $breadcrumbs = array();
    foreach ($ancestor_handles as $ancestor_handle) {
      $breadcrumbs[] = id(new PhabricatorCrumbView())
        ->setName($ancestor_handle->getName())
        ->setHref($ancestor_handle->getUri());
    }
    return $breadcrumbs;
  }

  protected function getDocumentSlug() {
    return '';
  }

}
