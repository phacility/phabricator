<?php

final class PhabricatorPeopleEmailLoginMailEngine
  extends PhabricatorPeopleMailEngine {

  public function validateMail() {
    $recipient = $this->getRecipient();

    if ($recipient->getIsDisabled()) {
      $this->throwValidationException(
        pht('User is Disabled'),
        pht(
          'You can not send an email login link to this email address '.
          'because the associated user account is disabled.'));
    }

    if (!$recipient->canEstablishWebSessions()) {
      $this->throwValidationException(
        pht('Not a Normal User'),
        pht(
          'You can not send an email login link to this email address '.
          'because the associated user account is not a normal user account '.
          'and can not log in to the web interface.'));
    }
  }

  protected function newMail() {
    $is_set_password = $this->isSetPasswordWorkflow();

    if ($is_set_password) {
      $subject = pht(
        '[%s] Account Password Link',
        PlatformSymbols::getPlatformServerName());
    } else {
      $subject = pht(
        '[%s] Account Login Link',
        PlatformSymbols::getPlatformServerName());
    }

    $recipient = $this->getRecipient();

    PhabricatorSystemActionEngine::willTakeAction(
      array($recipient->getPHID()),
      new PhabricatorAuthEmailLoginAction(),
      1);

    $engine = new PhabricatorAuthSessionEngine();
    $login_uri = $engine->getOneTimeLoginURI(
      $recipient,
      null,
      PhabricatorAuthSessionEngine::ONETIME_RESET);

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');
    $have_passwords = $this->isPasswordAuthEnabled();

    $body = array();

    if ($is_set_password) {
      $message_key = PhabricatorAuthEmailSetPasswordMessageType::MESSAGEKEY;
    } else {
      $message_key = PhabricatorAuthEmailLoginMessageType::MESSAGEKEY;
    }

    $message_body = PhabricatorAuthMessage::loadMessageText(
      $recipient,
      $message_key);
    if ($message_body !== null && strlen($message_body)) {
      $body[] = $this->newRemarkupText($message_body);
    }

    if ($have_passwords) {
      if ($is_set_password) {
        $body[] = pht(
          'You can use this link to set a password on your account:'.
          "\n\n  %s\n",
          $login_uri);
      } else if ($is_serious) {
        $body[] = pht(
          "You can use this link to reset your password:".
          "\n\n  %s\n",
          $login_uri);
      } else {
        $body[] = pht(
          "Condolences on forgetting your password. You can use this ".
          "link to reset it:\n\n".
          "  %s\n\n".
          "After you set a new password, consider writing it down on a ".
          "sticky note and attaching it to your monitor so you don't ".
          "forget again! Choosing a very short, easy-to-remember password ".
          "like \"cat\" or \"1234\" might also help.\n\n".
          "Best Wishes,\nPhabricator\n",
          $login_uri);

      }
    } else {
      $body[] = pht(
        "You can use this login link to regain access to your account:".
        "\n\n".
        "  %s\n",
        $login_uri);
    }

    $body = implode("\n\n", $body);

    return id(new PhabricatorMetaMTAMail())
      ->setSubject($subject)
      ->setBody($body);
  }

  private function isPasswordAuthEnabled() {
    return (bool)PhabricatorPasswordAuthProvider::getPasswordProvider();
  }

  private function isSetPasswordWorkflow() {
    $sender = $this->getSender();
    $recipient = $this->getRecipient();

    // Users can hit the "login with an email link" workflow while trying to
    // set a password on an account which does not yet have a password. We
    // require they verify that they own the email address and send them
    // through the email  login flow. In this case, the messaging is slightly
    // different.

    if ($sender->getPHID()) {
      if ($sender->getPHID() === $recipient->getPHID()) {
        return true;
      }
    }

    return false;
  }

}
