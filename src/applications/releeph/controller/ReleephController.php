<?php

abstract class ReleephController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName(pht('Releeph'));
    $page->setBaseURI('/releeph/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xD3\x82");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

}
