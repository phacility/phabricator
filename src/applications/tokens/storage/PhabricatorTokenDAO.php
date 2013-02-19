<?php

abstract class PhabricatorTokenDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'token';
  }

}
