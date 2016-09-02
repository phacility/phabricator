<?php

final class PhabricatorGuideWelcomeModule extends PhabricatorGuideModule {

  public function getModuleKey() {
    return 'welcome';
  }

  public function getModuleName() {
    return pht('Welcome');
  }

  public function getModulePosition() {
    return 10;
  }

  public function renderModuleStatus(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $content = pht(
      'You have successfully installed Phabricator. These next guides will '.
      'take you through configuration and new user orientation. '.
      'These steps are optional, and you can go through them in any order. '.
      'If you want to get back to this guide later on, you can find it in '.
      'the **Config** application under **Welcome Guide**.');

    $content = new PHUIRemarkupView($viewer, $content);

    return id(new PHUIDocumentViewPro())
      ->appendChild($content);

  }

}
