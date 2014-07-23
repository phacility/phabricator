<?php

final class ConpherenceCreateThreadMailReceiver
  extends PhabricatorMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorConpherenceApplication';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  public function canAcceptMail(PhabricatorMetaMTAReceivedMail $mail) {
    $usernames = $this->getMailUsernames($mail);
    if (!$usernames) {
      return false;
    }

    $users = $this->loadMailUsers($mail);
    if (count($users) != count($usernames)) {
      // At least some of the addresses are not users, so don't accept this as
      // a new Conpherence thread.
      return false;
    }

    return true;
  }

  private function getMailUsernames(PhabricatorMetaMTAReceivedMail $mail) {
    $usernames = array();
    foreach ($mail->getToAddresses() as $to_address) {
      $address = self::stripMailboxPrefix($to_address);
      $usernames[] = id(new PhutilEmailAddress($address))->getLocalPart();
    }

    return array_unique($usernames);
  }

  private function loadMailUsers(PhabricatorMetaMTAReceivedMail $mail) {
    $usernames = $this->getMailUsernames($mail);
    if (!$usernames) {
      return array();
    }

    return id(new PhabricatorUser())->loadAllWhere(
      'username in (%Ls)',
      $usernames);
  }

  protected function processReceivedMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorUser $sender) {

    $users = $this->loadMailUsers($mail);
    $phids = mpull($users, 'getPHID');

    $conpherence = id(new ConpherenceReplyHandler())
      ->setMailReceiver(ConpherenceThread::initializeNewThread($sender))
      ->setMailAddedParticipantPHIDs($phids)
      ->setActor($sender)
      ->setExcludeMailRecipientPHIDs($mail->loadExcludeMailRecipientPHIDs())
      ->processEmail($mail);

    if ($conpherence) {
      $mail->setRelatedPHID($conpherence->getPHID());
    }
  }

}
