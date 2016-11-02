<?php

final class PhabricatorCalendarNotification
  extends PhabricatorCalendarDAO {

  protected $eventPHID;
  protected $utcInitialEpoch;
  protected $targetPHID;
  protected $didNotifyEpoch;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'utcInitialEpoch' => 'epoch',
        'didNotifyEpoch' => 'epoch',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_notify' => array(
          'columns' => array('eventPHID', 'utcInitialEpoch', 'targetPHID'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
