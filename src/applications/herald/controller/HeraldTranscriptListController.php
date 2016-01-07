<?php

final class HeraldTranscriptListController extends HeraldController {

  public function buildSideNavView() {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new HeraldTranscriptSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addTextCrumb(
      pht('Transcripts'),
      $this->getApplicationURI('transcript/'));
    return $crumbs;
  }

  public function handleRequest(AphrontRequest $request) {
    return id(new HeraldTranscriptSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

}
