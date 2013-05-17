<?php

abstract class PhabricatorObjectMailReceiver extends PhabricatorMailReceiver {

  abstract protected function getObjectPattern();

  final public function canAcceptMail(PhabricatorMetaMTAReceivedMail $mail) {
    $regexp = $this->getAddressRegexp();

    foreach ($mail->getToAddresses() as $address) {
      $address = self::stripMailboxPrefix($address);
      $local = id(new PhutilEmailAddress($address))->getLocalPart();
      if (preg_match($regexp, $local)) {
        return true;
      }
    }

    return false;
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
