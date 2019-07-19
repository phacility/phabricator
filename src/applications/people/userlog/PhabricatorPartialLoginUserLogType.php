<?php

final class PhabricatorPartialLoginUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'login-partial';

  public function getLogTypeName() {
    return pht('Login: Partial Login');
  }

}
