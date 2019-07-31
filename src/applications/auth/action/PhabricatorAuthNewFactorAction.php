<?php

final class PhabricatorAuthNewFactorAction extends PhabricatorSystemAction {

  const TYPECONST = 'auth.factor.new';

  public function getScoreThreshold() {
    return 60 / phutil_units('1 hour in seconds');
  }

  public function getLimitExplanation() {
    return pht(
      'You have failed too many attempts to synchronize new multi-factor '.
      'authentication methods in a short period of time.');
  }

}
