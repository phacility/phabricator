<?php

final class PhabricatorProjectTriggerUsage
  extends PhabricatorProjectDAO {

  protected $triggerPHID;
  protected $examplePHID;
  protected $columnCount;
  protected $activeColumnCount;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'examplePHID' => 'phid?',
        'columnCount' => 'uint32',
        'activeColumnCount' => 'uint32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_trigger' => array(
          'columns' => array('triggerPHID'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
