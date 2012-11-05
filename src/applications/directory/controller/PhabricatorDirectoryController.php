<?php

abstract class PhabricatorDirectoryController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setBaseURI('/');
    $page->setTitle(idx($data, 'title'));

    $page->setGlyph("\xE2\x9A\x92");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  public function buildNav() {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/'));

    $nav->addLabel('Phabricator');
    $nav->addFilter('home', 'Tactical Command', '/');
    $nav->addFilter('jump', 'Jump Nav');
    $nav->addFilter('feed', 'Feed');
    $nav->addSpacer();
    $nav->addFilter('applications', 'More Stuff');

    return $nav;
  }

}
