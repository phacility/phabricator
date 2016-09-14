<?php

abstract class PhabricatorPackagesDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'packages';
  }

}
