<?php

final class DivinerLiveAtom extends DivinerDAO {

  protected $symbolPHID;
  protected $content;
  protected $atomData;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'content'  => self::SERIALIZATION_JSON,
        'atomData' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'symbolPHID' => array(
          'columns' => array('symbolPHID'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
