<?php

abstract class PhabricatorAuthMessageController
  extends PhabricatorAuthProviderController {

  protected function buildApplicationCrumbs() {
    return parent::buildApplicationCrumbs()
      ->addTextCrumb(pht('Messages'), $this->getApplicationURI('message/'));
  }

}
