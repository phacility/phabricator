<?php

final class PhabricatorGuideInstallController
  extends PhabricatorGuideController {

  public function shouldAllowPublic() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $title = pht('Installation Guide');

    $nav = $this->buildSideNavView();
    $nav->selectFilter('install/');

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setProfileHeader(true);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Installation'));

    $content = null;

    $view = id(new PHUICMSView())
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->setHeader($header)
      ->setContent($content);

    return $this->newPage()
      ->setTitle($title)
      ->addClass('phui-cms-body')
      ->appendChild($view);

  }

  private function getGuideContent() {

    $guide = null;

    return $guide;
  }
}
