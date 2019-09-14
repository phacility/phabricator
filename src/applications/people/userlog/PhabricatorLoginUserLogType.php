<?php

final class PhabricatorLoginUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'login';

  public function getLogTypeName() {
    return pht('Login');
  }

}
