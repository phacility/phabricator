<?php

final class PhabricatorAuthTryFactorAction extends PhabricatorSystemAction {

  const TYPECONST = 'auth.factor';

  public function getActionConstant() {
    return self::TYPECONST;
  }

  public function getScoreThreshold() {
    return 10 / phutil_units('1 hour in seconds');
  }

  public function getLimitExplanation() {
    return pht(
      'You have failed to verify multi-factor authentication too often in '.
      'a short period of time.');
  }

}
