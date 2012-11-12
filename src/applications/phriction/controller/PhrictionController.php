<?php

/**
 * @group phriction
 */
abstract class PhrictionController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();

    $page->setApplicationName('Phriction');
    $page->setBaseURI('/w/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x9A\xA1");

    $page->appendChild($view);
    $page->setSearchDefaultScope(PhabricatorSearchScope::SCOPE_WIKI);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }
}
