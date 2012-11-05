<?php

abstract class PhabricatorDaemonDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'daemon';
  }

}
