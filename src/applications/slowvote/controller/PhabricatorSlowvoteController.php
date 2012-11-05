<?php

/**
 * @group slowvote
 */
abstract class PhabricatorSlowvoteController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('Slowvote');
    $page->setBaseURI('/vote/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x9C\x94");

    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

}
