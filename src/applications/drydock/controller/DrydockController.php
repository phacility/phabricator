<?php

abstract class DrydockController extends PhabricatorController {

  abstract function buildSideNavView();

  protected function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

}
