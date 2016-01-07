<?php

abstract class PhamePostController extends PhameController {

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new PhamePostSearchEngine());
  }

}
