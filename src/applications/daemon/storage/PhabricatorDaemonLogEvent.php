<?php

final class PhabricatorDaemonLogEvent extends PhabricatorDaemonDAO {

  protected $logID;
  protected $logType;
  protected $message;
  protected $epoch;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'logType' => 'text4',
        'message' => 'text',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'logID' => array(
          'columns' => array('logID', 'epoch'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
