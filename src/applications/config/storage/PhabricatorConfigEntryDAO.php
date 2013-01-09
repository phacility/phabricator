<?php

abstract class PhabricatorConfigEntryDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'config';
  }

}
