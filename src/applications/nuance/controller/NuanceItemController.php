<?php

abstract class NuanceItemController
  extends NuanceController {

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new NuanceItemSearchEngine());
  }

}
