<?php

final class PhabricatorSignDocumentsUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'login-legalpad';

  public function getLogTypeName() {
    return pht('Login: Signed Required Legalpad Documents');
  }

}
