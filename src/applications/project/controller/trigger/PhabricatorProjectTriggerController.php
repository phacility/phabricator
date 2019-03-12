<?php

abstract class PhabricatorProjectTriggerController
  extends PhabricatorProjectController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addTextCrumb(
      pht('Triggers'),
      $this->getApplicationURI('trigger/'));

    return $crumbs;
  }

}
