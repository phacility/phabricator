<?php

abstract class PhabricatorBadgesDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'badges';
  }

}
