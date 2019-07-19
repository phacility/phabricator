<?php

final class PhabricatorAuthTryPasswordWithoutCAPTCHAAction
  extends PhabricatorSystemAction {

  const TYPECONST = 'auth.password-without-captcha';

  public function getActionConstant() {
    return self::TYPECONST;
  }

  public function getScoreThreshold() {
    return 10 / phutil_units('1 hour in seconds');
  }

}
