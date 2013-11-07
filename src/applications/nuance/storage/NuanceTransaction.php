<?php

abstract class NuanceTransaction
  extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'nuance';
  }

}
