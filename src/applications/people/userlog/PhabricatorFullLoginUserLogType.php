<?php

final class PhabricatorFullLoginUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'login-full';

  public function getLogTypeName() {
    return pht('Login: Upgrade to Full');
  }

}
