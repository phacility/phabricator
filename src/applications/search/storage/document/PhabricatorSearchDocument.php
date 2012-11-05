<?php

/**
 * @group search
 */
final class PhabricatorSearchDocument extends PhabricatorSearchDAO {

  protected $phid;
  protected $documentType;
  protected $documentTitle;
  protected $documentCreated;
  protected $documentModified;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_IDS        => self::IDS_MANUAL,
    ) + parent::getConfiguration();
  }

  public function getIDKey() {
    return 'phid';
  }

}
