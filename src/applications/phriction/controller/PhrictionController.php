<?php

abstract class PhrictionController extends PhabricatorController {

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    if ($for_app) {
      $nav->addFilter('create', pht('New Document'));
      $nav->addFilter('/phriction/', pht('Index'));
    }

    id(new PhrictionSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    if (get_class($this) != 'PhrictionListController') {
      $crumbs->addAction(
        id(new PHUIListItemView())
          ->setName(pht('Index'))
          ->setHref('/phriction/')
          ->setIcon('fa-home'));
    }

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('New Document'))
        ->setHref('/phriction/new/?slug='.$this->getDocumentSlug())
        ->setWorkflow(true)
        ->setIcon('fa-plus-square'));

    return $crumbs;
  }

  public function renderBreadcrumbs($slug) {
    $ancestor_handles = array();
    $ancestral_slugs = PhabricatorSlug::getAncestry($slug);
    $ancestral_slugs[] = $slug;
    if ($ancestral_slugs) {
      $empty_slugs = array_fill_keys($ancestral_slugs, null);
      $ancestors = id(new PhrictionDocumentQuery())
        ->setViewer($this->getRequest()->getUser())
        ->withSlugs($ancestral_slugs)
        ->execute();
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
      $breadcrumbs[] = id(new PHUICrumbView())
        ->setName($ancestor_handle->getName())
        ->setHref($ancestor_handle->getUri());
    }
    return $breadcrumbs;
  }

  protected function getDocumentSlug() {
    return '';
  }

}
