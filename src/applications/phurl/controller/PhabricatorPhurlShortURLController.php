<?php

final class PhabricatorPhurlShortURLController
  extends PhabricatorPhurlController {

  public function shouldRequireLogin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $append = $request->getURIData('append');
    $main_domain_uri = PhabricatorEnv::getProductionURI('/u/'.$append);

    return id(new AphrontRedirectResponse())
      ->setIsExternal(true)
      ->setURI($main_domain_uri);
  }
}
