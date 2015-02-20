<?php

abstract class PhabricatorPeopleInviteController
  extends PhabricatorPeopleController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Invites'),
      $this->getApplicationURI('invite/'));
    return $crumbs;
  }

}
