<?php

/**
 * @group legalpad
 */
final class LegalpadDocumentSignature extends LegalpadDAO {

  const VERIFIED = 0;
  const UNVERIFIED = 1;

  protected $documentPHID;
  protected $documentVersion;
  protected $signerPHID;
  protected $signatureData = array();
  protected $verified;
  protected $secretKey;

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'signatureData' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function save() {
    if (!$this->getSecretKey()) {
      $this->setSecretKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }

  public function isVerified() {
    return $this->getVerified() != self::UNVERIFIED;
  }

}
