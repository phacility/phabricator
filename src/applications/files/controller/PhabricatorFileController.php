<?php

abstract class PhabricatorFileController extends PhabricatorController {

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new PhabricatorFileSearchEngine());
  }

}
