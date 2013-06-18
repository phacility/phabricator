<?php

abstract class PhabricatorAuthDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'auth';
  }

}
