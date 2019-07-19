<?php

final class PhabricatorAuthTestSMSAction extends PhabricatorSystemAction {

  const TYPECONST = 'auth.sms.test';

  public function getScoreThreshold() {
    return 60 / phutil_units('1 hour in seconds');
  }

  public function getLimitExplanation() {
    return pht(
      'You and other users on this install are collectively sending too '.
      'many test text messages too quickly. Wait a few minutes to continue '.
      'texting tests.');
  }

}
