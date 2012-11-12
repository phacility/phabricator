<?php

abstract class PhabricatorProjectDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'project';
  }

}
