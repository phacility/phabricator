<?php

final class PhabricatorSearchIndexVersion
  extends PhabricatorSearchDAO {

  protected $objectPHID;
  protected $extensionKey;
  protected $version;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'extensionKey' => 'text64',
        'version' => 'text128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array(
          'columns' => array('objectPHID', 'extensionKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
