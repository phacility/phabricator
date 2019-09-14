<?php

final class PhabricatorAuthTryPasswordAction
  extends PhabricatorSystemAction {

  const TYPECONST = 'auth.password';

  public function getScoreThreshold() {
    return 100 / phutil_units('1 hour in seconds');
  }

  public function getLimitExplanation() {
    return pht(
      'Your remote address has made too many login attempts in a short '.
      'period of time.');
  }

}
