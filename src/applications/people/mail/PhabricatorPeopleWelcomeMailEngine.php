<?php

final class PhabricatorPeopleWelcomeMailEngine
  extends PhabricatorPeopleMailEngine {

  private $welcomeMessage;

  public function setWelcomeMessage($welcome_message) {
    $this->welcomeMessage = $welcome_message;
    return $this;
  }

  public function getWelcomeMessage() {
    return $this->welcomeMessage;
  }

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

    $recipient_username = $recipient->getUserName();
    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $base_uri = PhabricatorEnv::getProductionURI('/');

    $engine = new PhabricatorAuthSessionEngine();

    $uri = $engine->getOneTimeLoginURI(
      $recipient,
      $recipient->loadPrimaryEmail(),
      PhabricatorAuthSessionEngine::ONETIME_WELCOME);

    $message = array();

    $message[] = pht('Welcome to Phabricator!');

    $message[] = pht(
      '%s (%s) has created an account for you.',
      $sender->getUsername(),
      $sender->getRealName());

    $message[] = pht(
      '    Username: %s',
      $recipient->getUsername());

    // If password auth is enabled, give the user specific instructions about
    // how to add a credential to their account.

    // If we aren't sure what they're supposed to be doing and passwords are
    // not enabled, just give them generic instructions.

    $use_passwords = PhabricatorPasswordAuthProvider::getPasswordProvider();
    if ($use_passwords) {
      $message[] = pht(
        'To log in to Phabricator, follow this link and set a password:');
      $message[] = pht('  %s', $uri);
      $message[] = pht(
        'After you have set a password, you can log in to Phabricator in '.
        'the future by going here:');
      $message[] = pht('  %s', $base_uri);
    } else {
      $message[] = pht(
        'To log in to your account for the first time, follow this link:');
      $message[] = pht('  %s', $uri);
      $message[] = pht(
        'After you set up your account, you can log in to Phabricator in '.
        'the future by going here:');
      $message[] = pht('  %s', $base_uri);
    }

    $custom_body = $this->getWelcomeMessage();
    if (strlen($custom_body)) {
      $message[] = $custom_body;
    } else {
      if (!$is_serious) {
        $message[] = pht("Love,\nPhabricator");
      }
    }

    $message = implode("\n\n", $message);

    return id(new PhabricatorMetaMTAMail())
      ->addTos(array($recipient->getPHID()))
      ->setSubject(pht('[Phabricator] Welcome to Phabricator'))
      ->setBody($message);
  }

}
