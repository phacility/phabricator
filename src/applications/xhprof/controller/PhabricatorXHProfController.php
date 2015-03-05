<?php

abstract class PhabricatorXHProfController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('XHProf');
    $page->setBaseURI('/xhprof/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x98\x84");
    $page->appendChild($view);
    $page->setDeviceReady(true);

    $response = new AphrontWebpageResponse();

    if (isset($data['frame'])) {
      $response->setFrameable(true);
      $page->setFrameable(true);
      $page->setShowChrome(false);
      $page->setDisableConsole(true);
    }

    return $response->setContent($page->render());
  }

}
