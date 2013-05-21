<?php

abstract class PhabricatorHelpController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName(pht('Help'));
    $page->setBaseURI('/help/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph('?');
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

}
