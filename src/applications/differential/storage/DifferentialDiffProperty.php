<?php

final class DifferentialDiffProperty extends DifferentialDAO {

  protected $diffID;
  protected $name;
  protected $data;

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'data' => self::SERIALIZATION_JSON,
      )) + parent::getConfiguration();
  }

}
