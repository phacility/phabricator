<?php

final class PhabricatorAuthWelcomeMailMessageType
  extends PhabricatorAuthMessageType {

  const MESSAGEKEY = 'mail.welcome';

  public function getDisplayName() {
    return pht('Mail Body: Welcome');
  }

  public function getShortDescription() {
    return pht(
      'Custom instructions included in "Welcome" mail when an '.
      'administrator creates a user account.');
  }

}
