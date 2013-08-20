<?php

final class ReleephProjectTransaction
  extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'releeph';
  }

  public function getApplicationTransactionType() {
    return ReleephPHIDTypeProject::TYPECONST;
  }

}

