<?php

final class PhabricatorProjectTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME       = 'project:name';
  const TYPE_MEMBERS    = 'project:members';
  const TYPE_STATUS     = 'project:status';

  public function getApplicationName() {
    return 'project';
  }

  public function getApplicationTransactionType() {
    return PhabricatorProjectPHIDTypeProject::TYPECONST;
  }

}
