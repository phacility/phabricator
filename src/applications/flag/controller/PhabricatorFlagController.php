<?php

abstract class PhabricatorFlagController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();

    $page->setApplicationName('Flag');
    $page->setBaseURI('/flag/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x9A\x90"); // Subtle!
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());

  }
}
