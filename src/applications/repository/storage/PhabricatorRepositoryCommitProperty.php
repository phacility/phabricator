<?php

final class PhabricatorRepositoryCommitProperty
  extends PhabricatorRepositoryDAO {

  protected $commitID;
  protected $name;
  protected $data;

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'data' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'commitID' => array(
          'columns' => array('commitID', 'name'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
