<?php

abstract class NuanceSourceController
  extends NuanceController {

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new NuanceSourceSearchEngine());
  }

}
