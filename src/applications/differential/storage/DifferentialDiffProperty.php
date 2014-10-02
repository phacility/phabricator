<?php

final class DifferentialDiffProperty extends DifferentialDAO {

  protected $diffID;
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
        'diffID' => array(
          'columns' => array('diffID', 'name'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
