<?php

final class PhabricatorAuthEmailSetPasswordMessageType
  extends PhabricatorAuthMessageType {

  const MESSAGEKEY = 'mail.set-password';

  public function getDisplayName() {
    return pht('Mail Body: Set Password');
  }

  public function getShortDescription() {
    return pht(
      'Guidance in the message body when users set a password on an account '.
      'which did not previously have a password.');
  }

}
