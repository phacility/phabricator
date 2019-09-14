<?php

final class PhabricatorResetPasswordUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'reset-pass';

  public function getLogTypeName() {
    return pht('Reset Password');
  }

}
