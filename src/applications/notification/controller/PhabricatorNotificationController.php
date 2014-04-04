<?php

abstract class PhabricatorNotificationController
  extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();

    $page->setApplicationName(pht('Notification'));
    $page->setBaseURI('/notification/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph('!');
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());

  }

}
