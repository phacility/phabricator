<?php

abstract class DrydockController extends PhabricatorController {

  public abstract function buildSideNavView();

  protected function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

}
