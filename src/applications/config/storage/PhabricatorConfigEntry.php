<?php

final class PhabricatorConfigEntry extends PhabricatorConfigEntryDAO {

  protected $id;
  protected $phid;
  protected $namespace;
  protected $configKey;
  protected $value;
  protected $isDeleted;

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

  public static function loadConfigEntry($key) {
    $config_entry = id(new PhabricatorConfigEntry())
                 ->loadOneWhere(
                   'configKey = %s AND namespace = %s',
                    $key,
                   'default');

    if (!$config_entry) {
      $config_entry = id(new PhabricatorConfigEntry())
                   ->setConfigKey($key)
                   ->setNamespace('default');
    }

    return $config_entry;
  }

}
