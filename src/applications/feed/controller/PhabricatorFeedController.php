<?php

abstract class PhabricatorFeedController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName(pht('Feed'));
    $page->setBaseURI('/feed/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x88\x9E");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();

    if (!empty($data['public'])) {
      $page->setFrameable(true);
      $page->setShowChrome(false);
      $response->setFrameable(true);
    }

    return $response->setContent($page->render());
  }

  protected function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel(pht('Feed'));
    $nav->addFilter('all', pht('All Activity'));
    $nav->addFilter('projects', pht('My Projects'));

    return $nav;
  }

  protected function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

}
