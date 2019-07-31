<?php

final class PhabricatorAuthChangePasswordAction
  extends PhabricatorSystemAction {

  const TYPECONST = 'auth.password';

  public function getScoreThreshold() {
    return 20 / phutil_units('1 hour in seconds');
  }

  public function getLimitExplanation() {
    return pht(
      'You have failed to enter the correct account password too often in '.
      'a short period of time.');
  }

}
