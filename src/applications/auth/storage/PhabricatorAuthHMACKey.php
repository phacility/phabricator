<?php

final class PhabricatorAuthHMACKey
  extends PhabricatorAuthDAO {

  protected $keyName;
  protected $keyValue;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'keyName' => 'text64',
        'keyValue' => 'text128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_name' => array(
          'columns' => array('keyName'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
