<?php

abstract class PhabricatorUserDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'user';
  }

}
