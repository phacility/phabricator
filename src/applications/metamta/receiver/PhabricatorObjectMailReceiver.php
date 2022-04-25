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
    PhutilEmailAddress $target) {

    $parts = $this->matchObjectAddress($target);
    if (!$parts) {
      // We should only make it here if we matched already in "canAcceptMail()",
      // so this is a surprise.
      throw new Exception(
        pht(
          'Failed to parse object address ("%s") during processing.',
          (string)$target));
    }

    $pattern = $parts['pattern'];
    $sender = $this->getSender();

    try {
      $object = $this->loadObject($pattern, $sender);
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
            '("%s"), but public replies are not enabled on this server. An '.
            'administrator may have recently disabled this setting, or you '.
            'may have replied to an old message. Try replying to a more '.
            'recent message instead.',
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

    $mail_key = PhabricatorMetaMTAMailProperties::loadMailKey($object);
    $expect_hash = self::computeMailHash($mail_key, $check_phid);

    if (!phutil_hashes_are_identical($expect_hash, $parts['hash'])) {
      throw new PhabricatorMetaMTAReceivedMailProcessingException(
        MetaMTAReceivedMailStatus::STATUS_HASH_MISMATCH,
        pht(
          'This mail is addressed to an object ("%s"), but the address is '.
          'not correct (the security hash is wrong). Check that the address '.
          'is correct.',
          $pattern));
    }

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

  final public function canAcceptMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhutilEmailAddress $target) {

    // If we don't have a valid sender user account, we can never accept
    // mail to any object.
    $sender = $this->getSender();
    if (!$sender) {
      return false;
    }

    return (bool)$this->matchObjectAddress($target);
  }

  private function matchObjectAddress(PhutilEmailAddress $address) {
    $address = PhabricatorMailUtil::normalizeAddress($address);
    $local = $address->getLocalPart();

    $regexp = $this->getAddressRegexp();
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

  public static function computeMailHash($mail_key, $phid) {
    $hash = PhabricatorHash::digestWithNamedKey(
      $mail_key.$phid,
      'mail.object-address-key');
    return substr($hash, 0, 16);
  }

}
