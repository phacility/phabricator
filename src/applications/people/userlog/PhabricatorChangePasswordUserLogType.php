<?php

final class PhabricatorChangePasswordUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'change-password';

  public function getLogTypeName() {
    return pht('Change Password');
  }

}
