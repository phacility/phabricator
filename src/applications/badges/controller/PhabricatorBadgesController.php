<?php

abstract class PhabricatorBadgesController extends PhabricatorController {

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new PhabricatorBadgesSearchEngine());
  }

}
