<?php

abstract class PhabricatorCountdownController extends PhabricatorController {

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new PhabricatorCountdownSearchEngine());
  }
}
