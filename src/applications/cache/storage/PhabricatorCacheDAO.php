<?php

abstract class PhabricatorCacheDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'cache';
  }

}
