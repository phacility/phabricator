<?php

abstract class DifferentialController extends PhabricatorController {

  protected function allowsAnonymousAccess() {
    return PhabricatorEnv::getEnvConfig('differential.anonymous-access');
  }

  public function buildStandardPageResponse($view, array $data) {

    require_celerity_resource('differential-core-view-css');

    $viewer_is_anonymous = !$this->getRequest()->getUser()->isLoggedIn();

    $page = $this->buildStandardPageView();
    $page->setApplicationName('Differential');
    $page->setBaseURI('/differential/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x9A\x99");
    $page->appendChild($view);
    $page->setSearchDefaultScope(PhabricatorSearchScope::SCOPE_OPEN_REVISIONS);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

}
