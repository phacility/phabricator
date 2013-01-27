<?php

abstract class PhabricatorAuthController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName(pht('Login'));
    $page->setBaseURI('/login/');
    $page->setTitle(idx($data, 'title'));
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

}
