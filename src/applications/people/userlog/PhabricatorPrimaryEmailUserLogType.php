<?php

final class PhabricatorPrimaryEmailUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'email-primary';

  public function getLogTypeName() {
    return pht('Email: Change Primary');
  }

}
