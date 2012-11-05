<?php

abstract class PhabricatorFileController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('Files');
    $page->setBaseURI('/file/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x87\xAA");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

}
