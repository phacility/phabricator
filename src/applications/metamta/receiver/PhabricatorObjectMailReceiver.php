<?php

abstract class PhabricatorObjectMailReceiver extends PhabricatorMailReceiver {

  /**
   * Return a regular expression fragment which matches the name of an
   * object which can receive mail. For example, Differential uses:
   *
   *  D[1-9]\d*
   *
   * ...to match `D123`, etc., identifying Differential Revisions.
   *
   * @return string Regular expression fragment.
   */
  abstract protected function getObjectPattern();


  /**
   * Load the object receiving mail, based on an identifying pattern. Normally
   * this pattern is some sort of object ID.
   *
   * @param   string          A string matched by @{method:getObjectPattern}
   *                          fragment.
   * @param   PhabricatorUser The viewing user.
   * @return  void
   */
  abstract protected function loadObject($pattern, PhabricatorUser $viewer);


  public function validateSender(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorUser $sender) {
    parent::validateSender($mail, $sender);

    foreach ($mail->getToAddresses() as $address) {
      $parts = $this->matchObjectAddress($address);
      if ($parts) {
        break;
      }
    }

    try {
      $object = $this->loadObject($parts['pattern'], $sender);
    } catch (PhabricatorPolicyException $policy_exception) {
      throw new PhabricatorMetaMTAReceivedMailProcessingException(
        MetaMTAReceivedMailStatus::STATUS_POLICY_PROBLEM,
        pht(
          "This mail is addressed to an object you are not permitted ".
          "to see: %s",
          $policy_exception->getMessage()));
    }

    if (!$object) {
      throw new PhabricatorMetaMTAReceivedMailProcessingException(
        MetaMTAReceivedMailStatus::STATUS_NO_SUCH_OBJECT,
        pht(
          "This mail is addressed to an object ('%s'), but that object ".
          "does not exist.",
          $parts['pattern']));
    }

    $sender_identifier = $parts['sender'];

    if ($sender_identifier === 'public') {
      if (!PhabricatorEnv::getEnvConfig('metamta.public-replies')) {
        throw new PhabricatorMetaMTAReceivedMailProcessingException(
          MetaMTAReceivedMailStatus::STATUS_NO_PUBLIC_MAIL,
          pht(
            "This mail is addressed to an object's public address, but ".
            "public replies are not enabled (`metamta.public-replies`)."));
      }
      $check_phid = $object->getPHID();
    } else {
      if ($sender_identifier != $sender->getID()) {
        throw new PhabricatorMetaMTAReceivedMailProcessingException(
          MetaMTAReceivedMailStatus::STATUS_USER_MISMATCH,
          pht(
            "This mail is addressed to an object's private address, but ".
            "the sending user and the private address owner are not the ".
            "same user."));
      }
      $check_phid = $sender->getPHID();
    }

    $expect_hash = self::computeMailHash($object->getMailKey(), $check_phid);

    if ($expect_hash != $parts['hash']) {
      throw new PhabricatorMetaMTAReceivedMailProcessingException(
        MetaMTAReceivedMailStatus::STATUS_HASH_MISMATCH,
        pht(
          "The hash in this object's address does not match the expected ".
          "value."));
    }
  }


  final public function canAcceptMail(PhabricatorMetaMTAReceivedMail $mail) {
    foreach ($mail->getToAddresses() as $address) {
      if ($this->matchObjectAddress($address)) {
        return true;
      }
    }

    return false;
  }

  private function matchObjectAddress($address) {
    $regexp = $this->getAddressRegexp();

    $address = self::stripMailboxPrefix($address);
    $local = id(new PhutilEmailAddress($address))->getLocalPart();

    $matches = null;
    if (!preg_match($regexp, $local, $matches)) {
      return false;
    }

    return $matches;
  }

  private function getAddressRegexp() {
    $pattern = $this->getObjectPattern();

    $regexp =
      '(^'.
        '(?P<pattern>'.$pattern.')'.
        '\\+'.
        '(?P<sender>\w+)'.
        '\\+'.
        '(?P<hash>[a-f0-9]{16})'.
      '$)U';

    return $regexp;
  }

  public static function computeMailHash($mail_key, $phid) {
    $global_mail_key = PhabricatorEnv::getEnvConfig('phabricator.mail-key');

    $hash = PhabricatorHash::digest($mail_key.$global_mail_key.$phid);
    return substr($hash, 0, 16);
  }

}
