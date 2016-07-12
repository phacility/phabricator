<?php

final class PhabricatorFactCursor extends PhabricatorFactDAO {

  protected $name;
  protected $position;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text64',
        'position' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'name' => array(
          'columns' => array('name'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
