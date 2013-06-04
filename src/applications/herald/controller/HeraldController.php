<?php

abstract class HeraldController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName(pht('Herald'));
    $page->setBaseURI('/herald/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x98\xBF");

    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  public function buildApplicationMenu() {
    return $this->renderNav()->getMenu();
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PhabricatorMenuItemView())
        ->setName(pht('Create Herald Rule'))
        ->setHref($this->getApplicationURI('new/'))
        ->setIcon('create'));

    return $crumbs;
  }

  protected function renderNav() {
    $nav = id(new AphrontSideNavFilterView())
      ->setBaseURI(new PhutilURI('/herald/'))
      ->addLabel(pht('My Rules'))
      ->addFilter('new', pht('Create Rule'));

    $rules_map = HeraldContentTypeConfig::getContentTypeMap();
    foreach ($rules_map as $key => $value) {
      $nav->addFilter("view/{$key}/personal", $value);
    }

    $nav->addLabel(pht('Global Rules'));

    foreach ($rules_map as $key => $value) {
      $nav->addFilter("view/{$key}/global", $value);
    }

    $nav
      ->addLabel(pht('Utilities'))
      ->addFilter('test',       pht('Test Console'))
      ->addFilter('transcript', pht('Transcripts'))
      ->addFilter('history',    pht('Edit Log'));

    if ($this->getRequest()->getUser()->getIsAdmin()) {
      $nav->addLabel(pht('Admin'));
      foreach ($rules_map as $key => $value) {
        $nav->addFilter("view/{$key}/all", $value);
      }
    }

    return $nav;
  }

}
