<?php

/**
 * @group search
 */
final class PhabricatorSearchDocumentField extends PhabricatorSearchDAO {

  protected $phid;
  protected $field;
  protected $auxPHID;
  protected $corpus;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_IDS        => self::IDS_MANUAL,
    ) + parent::getConfiguration();
  }

}
