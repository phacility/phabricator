<?php

abstract class DrydockController extends PhabricatorController {

  abstract public function buildSideNavView();

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

}
