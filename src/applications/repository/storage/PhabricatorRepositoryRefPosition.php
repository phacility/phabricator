<?php

final class PhabricatorRepositoryRefPosition
  extends PhabricatorRepositoryDAO {

  protected $cursorID;
  protected $commitIdentifier;
  protected $isClosed = 0;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'commitIdentifier' => 'text40',
        'isClosed' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_position' => array(
          'columns' => array('cursorID', 'commitIdentifier'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
