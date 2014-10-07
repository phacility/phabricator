<?php

/**
 * This is useful for testing, but otherwise your SMS ends up in a blackhole.
 */
final class PhabricatorSMSImplementationTestBlackholeAdapter
  extends PhabricatorSMSImplementationAdapter {

  public function getProviderShortName() {
    return 'testtesttest';
  }

  public function send() {
    // I guess this is what a blackhole looks like
  }

  public function getSMSDataFromResult($result) {
    return array(
      Filesystem::readRandomCharacters(40),
      PhabricatorSMS::STATUS_SENT,
    );
  }

  public function pollSMSSentStatus(PhabricatorSMS $sms) {
    if ($sms->getID()) {
      return PhabricatorSMS::STATUS_SENT;
    }
    return PhabricatorSMS::STATUS_SENT_UNCONFIRMED;
  }

}
