<?php

final class PhabricatorSMS
  extends PhabricatorSMSDAO {

  const MAXIMUM_SEND_TRIES        = 5;

  /**
   * Status constants should be 16 characters or less. See status entries
   * for details on what they indicate about the underlying SMS.
   */

  // in the beginning, all SMS are unsent
  const STATUS_UNSENT             = 'unsent';
  // that nebulous time when we've sent it from Phabricator but haven't
  // heard anything from the external API
  const STATUS_SENT_UNCONFIRMED   = 'sent-unconfirmed';
  // "success"
  const STATUS_SENT               = 'sent';
  // "fail" but we'll try again
  const STATUS_FAILED             = 'failed';
  // we're giving up on our external API partner
  const STATUS_FAILED_PERMANENTLY = 'permafailed';

  const SHORTNAME_PLACEHOLDER     = 'phabricator';

  protected $providerShortName;
  protected $providerSMSID;
  // numbers can be up to 20 digits long
  protected $toNumber;
  protected $fromNumber;
  protected $body;
  protected $sendStatus;

  public static function initializeNewSMS($body) {
    // NOTE: these values will be updated to correct values when the
    // SMS is sent for the first time. In particular, the ProviderShortName
    // and ProviderSMSID are totally garbage data before a send it attempted.
    return id(new PhabricatorSMS())
      ->setBody($body)
      ->setSendStatus(self::STATUS_UNSENT)
      ->setProviderShortName(self::SHORTNAME_PLACEHOLDER)
      ->setProviderSMSID(Filesystem::readRandomCharacters(40));
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'providerShortName' => 'text16',
        'providerSMSID' => 'text40',
        'toNumber' => 'text20',
        'fromNumber' => 'text20?',
        'body' => 'text',
        'sendStatus' => 'text16?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_provider' => array(
          'columns' => array('providerSMSID', 'providerShortName'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getTableName() {
    // Slightly non-standard, but otherwise this class needs "MetaMTA" in its
    // name. :/
    return 'sms';
  }

  public function hasBeenSentAtLeastOnce() {
    return ($this->getProviderShortName() !=
      self::SHORTNAME_PLACEHOLDER);
  }
}
