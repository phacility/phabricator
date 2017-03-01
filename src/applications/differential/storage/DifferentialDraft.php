<?php

final class DifferentialDraft extends DifferentialDAO {

  protected $objectPHID;
  protected $authorPHID;
  protected $draftKey;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'draftKey' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_unique' => array(
          'columns' => array('objectPHID', 'authorPHID', 'draftKey'),
          'unique' => true,
        ),
      ),
    )  + parent::getConfiguration();
  }

}
