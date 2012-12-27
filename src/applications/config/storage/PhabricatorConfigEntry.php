<?php

final class PhabricatorConfigEntry extends PhabricatorConfigEntryDAO {

  protected $id;
  protected $phid;
  protected $namespace;
  protected $configKey;
  protected $value;

  // TODO: Remove this default when implementing Transactions.
  protected $isDeleted = 0;

  public function getURI() {
    return '/config/edit/'.$this->configKey;
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'value' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_CONF);
  }

}
