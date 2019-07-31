<?php

final class PhabricatorSettingsAddEmailAction extends PhabricatorSystemAction {

  const TYPECONST = 'email.add';

  public function getScoreThreshold() {
    return 6 / phutil_units('1 hour in seconds');
  }

  public function getLimitExplanation() {
    return pht(
      'You are adding too many email addresses to your account too quickly.');
  }

}
