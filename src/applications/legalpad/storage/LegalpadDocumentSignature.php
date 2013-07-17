<?php

/**
 * @group legalpad
 */
final class LegalpadDocumentSignature extends LegalpadDAO {

  protected $documentPHID;
  protected $documentVersion;
  protected $signerPHID;
  protected $signatureData = array();

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'signatureData' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }



}
