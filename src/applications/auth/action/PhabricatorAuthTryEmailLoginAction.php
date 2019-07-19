<?php

final class PhabricatorAuthTryEmailLoginAction
  extends PhabricatorSystemAction {

  const TYPECONST = 'mail.try-login';

  public function getScoreThreshold() {
    return 20 / phutil_units('1 hour in seconds');
  }

  public function getLimitExplanation() {
    return pht(
      'You have made too many account recovery requests in a short period '.
      'of time.');
  }

}
