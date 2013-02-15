<?php

final class PhabricatorTokenCount extends PhabricatorTokenDAO {

  protected $objectPHID;
  protected $tokenCount;

  public function getConfiguration() {
    return array(
      self::CONFIG_IDS => self::IDS_MANUAL,
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

}
