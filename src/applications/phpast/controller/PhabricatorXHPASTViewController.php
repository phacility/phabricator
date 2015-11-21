<?php

abstract class PhabricatorXHPASTViewController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('XHPASTView');
    $page->setBaseURI('/xhpast/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x96\xA0");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

}
