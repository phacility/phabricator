<?php

abstract class HeraldController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('Herald');
    $page->setBaseURI('/herald/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x98\xBF");

    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  protected function renderNav() {
    $nav = id(new AphrontSideNavFilterView())
      ->setBaseURI(new PhutilURI('/herald/'))
      ->addLabel('My Rules')
      ->addFilter('new', 'Create Rule');

    $rules_map = HeraldContentTypeConfig::getContentTypeMap();
    foreach ($rules_map as $key => $value) {
      $nav->addFilter("view/{$key}/personal", $value);
    }

    $nav->addLabel('Global Rules');

    foreach ($rules_map as $key => $value) {
      $nav->addFilter("view/{$key}/global", $value);
    }

    $nav
      ->addLabel('Utilities')
      ->addFilter('test',       'Test Console')
      ->addFilter('transcript', 'Transcripts')
      ->addFilter('history',    'Edit Log');

    if ($this->getRequest()->getUser()->getIsAdmin()) {
      $nav->addLabel('Admin');
      foreach ($rules_map as $key => $value) {
        $nav->addFilter("view/{$key}/all", $value);
      }
    }

    return $nav;
  }

}
