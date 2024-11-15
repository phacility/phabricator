<?php

abstract class PhabricatorRoleTriggerController
  extends PhabricatorRoleController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addTextCrumb(
      pht('Triggers'),
      $this->getApplicationURI('trigger/'));

    return $crumbs;
  }

}
