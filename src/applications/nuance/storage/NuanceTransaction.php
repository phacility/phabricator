<?php

abstract class NuanceTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'nuance';
  }

}
