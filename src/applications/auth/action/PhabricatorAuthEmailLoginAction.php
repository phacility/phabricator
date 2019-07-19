<?php

final class PhabricatorAuthEmailLoginAction extends PhabricatorSystemAction {

  const TYPECONST = 'mail.login';

  public function getScoreThreshold() {
    return 3 / phutil_units('1 hour in seconds');
  }

  public function getLimitExplanation() {
    return pht(
      'Too many account recovery email links have been sent to this account '.
      'in a short period of time.');
  }

}
