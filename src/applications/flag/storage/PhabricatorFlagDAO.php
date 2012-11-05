<?php

abstract class PhabricatorFlagDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'flag';
  }

}
