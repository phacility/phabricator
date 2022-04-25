<?php

final class PhabricatorPeopleUsernameMailEngine
  extends PhabricatorPeopleMailEngine {

  private $oldUsername;
  private $newUsername;

  public function setNewUsername($new_username) {
    $this->newUsername = $new_username;
    return $this;
  }

  public function getNewUsername() {
    return $this->newUsername;
  }

  public function setOldUsername($old_username) {
    $this->oldUsername = $old_username;
    return $this;
  }

  public function getOldUsername() {
    return $this->oldUsername;
  }

  public function validateMail() {
    return;
  }

  protected function newMail() {
    $sender = $this->getSender();

    $sender_username = $sender->getUsername();
    $sender_realname = $sender->getRealName();

    $old_username = $this->getOldUsername();
    $new_username = $this->getNewUsername();

    $body = sprintf(
      "%s\n\n  %s\n  %s\n",
      pht(
        '%s (%s) has changed your %s username.',
        $sender_username,
        $sender_realname,
        PlatformSymbols::getPlatformServerName()),
      pht(
        'Old Username: %s',
        $old_username),
      pht(
        'New Username: %s',
        $new_username));

    return id(new PhabricatorMetaMTAMail())
      ->setSubject(
        pht(
          '[%s] Username Changed',
          PlatformSymbols::getPlatformServerName()))
      ->setBody($body);
  }

}
