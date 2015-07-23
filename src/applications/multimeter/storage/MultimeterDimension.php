<?php

abstract class MultimeterDimension extends MultimeterDAO {

  protected $name;
  protected $nameHash;

  public function setName($name) {
    $this->nameHash = PhabricatorHash::digestForIndex($name);
    return parent::setName($name);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text',
        'nameHash' => 'bytes12',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_hash' => array(
          'columns' => array('nameHash'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
