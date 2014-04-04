<?php

abstract class PhabricatorSystemDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'system';
  }

}
