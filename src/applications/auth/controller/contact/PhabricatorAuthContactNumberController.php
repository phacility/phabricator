<?php

abstract class PhabricatorAuthContactNumberController
  extends PhabricatorAuthController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addTextCrumb(
      pht('Contact Numbers'),
      pht('/settings/panel/contact/'));

    return $crumbs;
  }

}
