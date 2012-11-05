<?php

abstract class DrydockController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();

    $page->setApplicationName('Drydock');
    $page->setBaseURI('/drydock/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x98\x82");

    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  final protected function buildSideNav($selected) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/drydock/'));
    $nav->addFilter('resource', 'Resources');
    $nav->addFilter('lease',    'Leases');
    $nav->addSpacer();
    $nav->addFilter('log',      'Logs');

    $nav->selectFilter($selected, 'resource');

    return $nav;
  }

}
