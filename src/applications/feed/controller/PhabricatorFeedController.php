<?php

abstract class PhabricatorFeedController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('Feed');
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

}
