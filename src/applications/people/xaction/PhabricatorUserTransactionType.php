<?php

abstract class PhabricatorUserTransactionType
  extends PhabricatorModularTransactionType {

  protected function newUserLog($action) {
    return PhabricatorUserLog::initializeNewLog(
      $this->getActor(),
      $this->getObject()->getPHID(),
      $action);
  }

}
