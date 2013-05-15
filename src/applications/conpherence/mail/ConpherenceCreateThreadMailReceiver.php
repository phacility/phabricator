<?php

final class ConpherenceCreateThreadMailReceiver
  extends PhabricatorMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorApplicationConpherence';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  public function canAcceptMail(PhabricatorMetaMTAReceivedMail $mail) {
    $usernames = array();
    foreach ($mail->getToAddresses() as $to_address) {
      $address = self::stripMailboxPrefix($to_address);
      $usernames[] = id(new PhutilEmailAddress($address))->getLocalPart();
    }

    $usernames = array_unique($usernames);

    if (!$usernames) {
      return false;
    }

    $users = id(new PhabricatorUser())->loadAllWhere(
      'username in (%Ls)',
      $usernames);

    if (count($users) != count($usernames)) {
      // At least some of the addresses are not users, so don't accept this as
      // a new Conpherence thread.
      return false;
    }

    return true;
  }

}
