<?php

abstract class PhabricatorAuthFactorProviderController
  extends PhabricatorAuthProviderController {

  protected function buildApplicationCrumbs() {
    return parent::buildApplicationCrumbs()
      ->addTextCrumb(pht('Multi-Factor'), $this->getApplicationURI('mfa/'));
  }

}
