<?php

abstract class NuanceQueueController
  extends NuanceController {

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new NuanceQueueSearchEngine());
  }

}
