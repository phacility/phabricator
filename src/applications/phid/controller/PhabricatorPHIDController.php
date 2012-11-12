<?php

abstract class PhabricatorPHIDController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('PHID');
    $page->setBaseURI('/phid/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph('#');
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

}
