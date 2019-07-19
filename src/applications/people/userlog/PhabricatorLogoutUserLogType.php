<?php

final class PhabricatorLogoutUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'logout';

  public function getLogTypeName() {
    return pht('Logout');
  }

}
