<?php

abstract class PhabricatorCountdownController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();

    $page->setApplicationName('Countdown');
    $page->setBaseURI('/countdown/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x9A\xB2");
    $page->setShowChrome(idx($data, 'chrome', true));

    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());

  }
}
