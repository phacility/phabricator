<?php

final class PhabricatorDaemonLockLog
  extends PhabricatorDaemonDAO {

  protected $lockName;
  protected $lockReleased;
  protected $lockParameters = array();
  protected $lockContext = array();

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'lockParameters' => self::SERIALIZATION_JSON,
        'lockContext' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'lockName' => 'text64',
        'lockReleased' => 'epoch?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_lock' => array(
          'columns' => array('lockName'),
        ),
        'key_created' => array(
          'columns' => array('dateCreated'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
