<?php

abstract class PhameBlogController extends PhameController {

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new PhameBlogSearchEngine());
  }

}
