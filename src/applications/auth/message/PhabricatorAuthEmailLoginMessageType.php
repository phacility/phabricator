<?php

final class PhabricatorAuthEmailLoginMessageType
  extends PhabricatorAuthMessageType {

  const MESSAGEKEY = 'mail.login';

  public function getDisplayName() {
    return pht('Mail Body: Email Login');
  }

  public function getShortDescription() {
    return pht(
      'Guidance in the message body when users request an email link '.
      'to access their account.');
  }

}
