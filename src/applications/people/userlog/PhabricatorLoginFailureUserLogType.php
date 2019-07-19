<?php

final class PhabricatorLoginFailureUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'login-fail';

  public function getLogTypeName() {
    return pht('Login: Failure');
  }

}
