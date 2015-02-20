<?php

final class PhabricatorTokenCount extends PhabricatorTokenDAO {

  protected $objectPHID;
  protected $tokenCount;

  protected function getConfiguration() {
    return array(
      self::CONFIG_IDS => self::IDS_MANUAL,
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'id' => 'auto',
        'tokenCount' => 'uint32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_objectPHID' => array(
          'columns' => array('objectPHID'),
          'unique' => true,
        ),
        'key_count' => array(
          'columns' => array('tokenCount'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
