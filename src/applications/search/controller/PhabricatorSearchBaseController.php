<?php

abstract class PhabricatorSearchBaseController extends PhabricatorController {

  const ACTION_ATTACH       = 'attach';
  const ACTION_MERGE        = 'merge';
  const ACTION_DEPENDENCIES = 'dependencies';
  const ACTION_BLOCKS       = 'blocks';
  const ACTION_EDGE         = 'edge';

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
