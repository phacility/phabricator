<?php

abstract class DrydockController extends PhabricatorController {

  abstract function buildSideNavView();

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

}
