<?php

/**
 * @group search
 */
abstract class PhabricatorSearchBaseController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('Search');
    $page->setBaseURI('/search/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xC2\xBF");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

}
