<?php

abstract class PhabricatorSlowvoteController extends PhabricatorController {

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new PhabricatorSlowvoteSearchEngine());
  }

}
