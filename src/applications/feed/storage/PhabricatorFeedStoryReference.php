<?php

final class PhabricatorFeedStoryReference extends PhabricatorFeedDAO {

  protected $objectPHID;
  protected $chronologicalKey;

  protected function getConfiguration() {
    return array(
      self::CONFIG_IDS          => self::IDS_MANUAL,
      self::CONFIG_TIMESTAMPS   => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'chronologicalKey' => 'uint64',
        'id' => null,
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'PRIMARY' => null,
        'objectPHID' => array(
          'columns' => array('objectPHID', 'chronologicalKey'),
          'unique' => true,
        ),
        'chronologicalKey' => array(
          'columns' => array('chronologicalKey'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
