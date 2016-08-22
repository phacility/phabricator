<?php

final class PhabricatorGuideWelcomeController
  extends PhabricatorGuideController {

  public function shouldAllowPublic() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    require_celerity_resource('guides-app-css');
    $viewer = $request->getViewer();

    $title = pht('Welcome to Phabricator');

    $nav = $this->buildSideNavView();
    $nav->selectFilter('/');

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setProfileHeader(true);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Welcome'));

    $content = id(new PHUIDocumentViewPro())
      ->appendChild($this->getGuideContent($viewer));

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

  private function getGuideContent($viewer) {

    $content = pht(
      'You have successfully installed Phabricator. These next guides will '.
      'take you through configuration and new user orientation. '.
      'These steps are optional, and you can go through them in any order. '.
      'If you want to get back to this guide later on, you can find it in '.
      'the **Config** application under **Welcome Guide**.');

    return new PHUIRemarkupView($viewer, $content);
  }
}
