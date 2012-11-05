<?php

abstract class PhabricatorWorkerDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'worker';
  }

}
