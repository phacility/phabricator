<?php

abstract class DrydockController extends PhabricatorController {

  public abstract function buildSideNavView();

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

}
