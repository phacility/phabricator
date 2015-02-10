<?php

final class PhabricatorAuthInvite
  extends PhabricatorUserDAO {

  protected $authorPHID;
  protected $emailAddress;
  protected $verificationHash;
  protected $acceptedByPHID;

  private $verificationCode;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'emailAddress' => 'sort128',
        'verificationHash' => 'bytes12',
        'acceptedByPHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_address' => array(
          'columns' => array('emailAddress'),
          'unique' => true,
        ),
        'key_code' => array(
          'columns' => array('verificationHash'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getVerificationCode() {
    if (!$this->getVerificationHash()) {
      if ($this->verificationHash) {
        throw new Exception(
          pht(
            'Verification code can not be regenerated after an invite is '.
            'created.'));
      }
      $this->verificationCode = Filesystem::readRandomCharacters(16);
    }
    return $this->verificationCode;
  }

  public function save() {
    if (!$this->getVerificationHash()) {
      $hash = PhabricatorHash::digestForIndex($this->getVerificationCode());
      $this->setVerificationHash($hash);
    }

    return parent::save();
  }

}
