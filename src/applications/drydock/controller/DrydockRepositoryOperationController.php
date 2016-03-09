<?php

abstract class DrydockRepositoryOperationController
  extends DrydockController {

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new DrydockRepositoryOperationSearchEngine());
  }

}
