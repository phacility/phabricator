<?php

final class PhabricatorPeopleWelcomeMailEngine
  extends PhabricatorPeopleMailEngine {

  public function validateMail() {
    $sender = $this->getSender();
    $recipient = $this->getRecipient();

    if (!$sender->getIsAdmin()) {
      $this->throwValidationException(
        pht('Not an Administrator'),
        pht(
          'You can not send welcome mail because you are not an '.
          'administrator. Only administrators may send welcome mail.'));
    }

    if ($recipient->getIsDisabled()) {
      $this->throwValidationException(
        pht('User is Disabled'),
        pht(
          'You can not send welcome mail to this user because their account '.
          'is disabled.'));
    }

    if (!$recipient->canEstablishWebSessions()) {
      $this->throwValidationException(
        pht('Not a Normal User'),
        pht(
          'You can not send this user welcome mail because they are not '.
          'a normal user and can not log in to the web interface. Special '.
          'users (like bots and mailing lists) are unable to establish '.
          'web sessions.'));
    }
  }

  protected function newMail() {
    $sender = $this->getSender();
    $recipient = $this->getRecipient();

    $sender_username = $sender->getUserName();
    $sender_realname = $sender->getRealName();

    $recipient_username = $recipient->getUserName();
    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $base_uri = PhabricatorEnv::getProductionURI('/');

    $engine = new PhabricatorAuthSessionEngine();

    $uri = $engine->getOneTimeLoginURI(
      $recipient,
      $recipient->loadPrimaryEmail(),
      PhabricatorAuthSessionEngine::ONETIME_WELCOME);

    $body = pht(
      "Welcome to Phabricator!\n\n".
      "%s (%s) has created an account for you.\n\n".
      "  Username: %s\n\n".
      "To login to Phabricator, follow this link and set a password:\n\n".
      "  %s\n\n".
      "After you have set a password, you can login in the future by ".
      "going here:\n\n".
      "  %s\n",
      $sender_username,
      $sender_realname,
      $recipient_username,
      $uri,
      $base_uri);

    if (!$is_serious) {
      $body .= sprintf(
        "\n%s\n",
        pht("Love,\nPhabricator"));
    }

    return id(new PhabricatorMetaMTAMail())
      ->addTos(array($recipient->getPHID()))
      ->setSubject(pht('[Phabricator] Welcome to Phabricator'))
      ->setBody($body);
  }

}
