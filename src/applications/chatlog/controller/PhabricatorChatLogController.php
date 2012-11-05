<?php

abstract class PhabricatorChatLogController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('Chat Log');
    $page->setBaseURI('/chatlog/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph('#');
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

}
