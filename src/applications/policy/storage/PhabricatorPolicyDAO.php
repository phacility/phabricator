<?php

abstract class PhabricatorPolicyDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'policy';
  }

}
