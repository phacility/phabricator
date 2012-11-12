<?php

abstract class PhabricatorAuditController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();

    $page->setApplicationName('Audit');
    $page->setBaseURI('/audit/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x9C\x8D");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());

  }
}
