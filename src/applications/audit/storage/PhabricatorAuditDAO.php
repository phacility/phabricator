<?php

abstract class PhabricatorAuditDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'audit';
  }

}
