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

  public function getFullDescription() {
    return pht(
      'Guidance included in the mail message body when users request an '.
      'email link to access their account.'.
      "\n\n".
      'For installs with password authentication enabled, users access this '.
      'workflow by using the "Forgot your password?" link on the login '.
      'screen.'.
      "\n\n".
      'For installs without password authentication enabled, users access '.
      'this workflow by using the "Send a login link to your email address." '.
      'link on the login screen. This workflow allows users to recover '.
      'access to their account if there is an issue with an external '.
      'login service.');
  }

  public function getDefaultMessageText() {
    return pht(
      'You (or someone pretending to be you) recently requested an account '.
      'recovery link be sent to this email address. If you did not make '.
      'this request, you can ignore this message.');
  }

}
