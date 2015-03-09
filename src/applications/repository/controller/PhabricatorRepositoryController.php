<?php

abstract class PhabricatorRepositoryController extends PhabricatorController {

  public function shouldRequireAdmin() {
    // Most of these controllers are admin-only.
    return true;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    return $crumbs;
  }

}
