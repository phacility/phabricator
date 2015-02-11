<?php

final class PhabricatorSystemDestructionLog extends PhabricatorSystemDAO {

  protected $objectClass;
  protected $rootLogID;
  protected $objectPHID;
  protected $objectMonogram;
  protected $epoch;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'objectClass' => 'text128',
        'rootLogID' => 'id?',
        'objectPHID' => 'phid?',
        'objectMonogram' => 'text64?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_epoch' => array(
          'columns' => array('epoch'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
