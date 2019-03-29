<?php

abstract class PhabricatorDashboardPortalController
  extends PhabricatorDashboardController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addTextCrumb(pht('Portals'), '/portal/');

    return $crumbs;
  }

}
