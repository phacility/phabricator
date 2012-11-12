<?php

abstract class PhabricatorFactController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setBaseURI('/fact/');
    $page->setTitle(idx($data, 'title'));

    $page->setGlyph("\xCE\xA3");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

}
