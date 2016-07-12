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


  final protected function processReceivedMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorUser $sender) {

    $object = $this->loadObjectFromMail($mail, $sender);
    $mail->setRelatedPHID($object->getPHID());

    $this->processReceivedObjectMail($mail, $object, $sender);

    return $this;
  }

  protected function processReceivedObjectMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorLiskDAO $object,
    PhabricatorUser $sender) {

    $handler = $this->getTransactionReplyHandler();
    if ($handler) {
      return $handler
        ->setMailReceiver($object)
        ->setActor($sender)
        ->setExcludeMailRecipientPHIDs($mail->loadAllRecipientPHIDs())
        ->processEmail($mail);
    }

    throw new PhutilMethodNotImplementedException();
  }

  protected function getTransactionReplyHandler() {
    return null;
  }

  public function loadMailReceiverObject($pattern, PhabricatorUser $viewer) {
    return $this->loadObject($pattern, $viewer);
  }

  public function validateSender(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorUser $sender) {

    parent::validateSender($mail, $sender);

    $parts = $this->matchObjectAddressInMail($mail);
    $pattern = $parts['pattern'];

    try {
      $object = $this->loadObjectFromMail($mail, $sender);
    } catch (PhabricatorPolicyException $policy_exception) {
      throw new PhabricatorMetaMTAReceivedMailProcessingException(
        MetaMTAReceivedMailStatus::STATUS_POLICY_PROBLEM,
        pht(
          'This mail is addressed to an object ("%s") you do not have '.
          'permission to see: %s',
          $pattern,
          $policy_exception->getMessage()));
    }

    if (!$object) {
      throw new PhabricatorMetaMTAReceivedMailProcessingException(
        MetaMTAReceivedMailStatus::STATUS_NO_SUCH_OBJECT,
        pht(
          'This mail is addressed to an object ("%s"), but that object '.
          'does not exist.',
          $pattern));
    }

    $sender_identifier = $parts['sender'];

    if ($sender_identifier === 'public') {
      if (!PhabricatorEnv::getEnvConfig('metamta.public-replies')) {
        throw new PhabricatorMetaMTAReceivedMailProcessingException(
          MetaMTAReceivedMailStatus::STATUS_NO_PUBLIC_MAIL,
          pht(
            'This mail is addressed to the public email address of an object '.
            '("%s"), but public replies are not enabled on this Phabricator '.
            'install. An administrator may have recently disabled this '.
            'setting, or you may have replied to an old message. Try '.
            'replying to a more recent message instead.',
            $pattern));
      }
      $check_phid = $object->getPHID();
    } else {
      if ($sender_identifier != $sender->getID()) {
        throw new PhabricatorMetaMTAReceivedMailProcessingException(
          MetaMTAReceivedMailStatus::STATUS_USER_MISMATCH,
          pht(
            'This mail is addressed to the private email address of an object '.
            '("%s"), but you are not the user who is authorized to use the '.
            'address you sent mail to. Each private address is unique to the '.
            'user who received the original mail. Try replying to a message '.
            'which was sent directly to you instead.',
            $pattern));
      }
      $check_phid = $sender->getPHID();
    }

    $expect_hash = self::computeMailHash($object->getMailKey(), $check_phid);

    if (!phutil_hashes_are_identical($expect_hash, $parts['hash'])) {
      throw new PhabricatorMetaMTAReceivedMailProcessingException(
        MetaMTAReceivedMailStatus::STATUS_HASH_MISMATCH,
        pht(
          'This mail is addressed to an object ("%s"), but the address is '.
          'not correct (the security hash is wrong). Check that the address '.
          'is correct.',
          $pattern));
    }
  }


  final public function canAcceptMail(PhabricatorMetaMTAReceivedMail $mail) {
    if ($this->matchObjectAddressInMail($mail)) {
      return true;
    }

    return false;
  }

  private function matchObjectAddressInMail(
    PhabricatorMetaMTAReceivedMail $mail) {

    foreach ($mail->getToAddresses() as $address) {
      $parts = $this->matchObjectAddress($address);
      if ($parts) {
        return $parts;
      }
    }

    return null;
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
      '$)Ui';

    return $regexp;
  }

  private function loadObjectFromMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorUser $sender) {
    $parts = $this->matchObjectAddressInMail($mail);

    return $this->loadObject(
      phutil_utf8_strtoupper($parts['pattern']),
      $sender);
  }

  public static function computeMailHash($mail_key, $phid) {
    $global_mail_key = PhabricatorEnv::getEnvConfig('phabricator.mail-key');

    $hash = PhabricatorHash::digest($mail_key.$global_mail_key.$phid);
    return substr($hash, 0, 16);
  }

}
