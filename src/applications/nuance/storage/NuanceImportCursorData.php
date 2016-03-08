<?php

final class NuanceImportCursorData
  extends NuanceDAO {

  protected $sourcePHID;
  protected $cursorKey;
  protected $cursorType;
  protected $properties = array();

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'cursorType' => 'text32',
        'cursorKey' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_source' => array(
          'columns' => array('sourcePHID', 'cursorKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      NuanceImportCursorPHIDType::TYPECONST);
  }

  public function getCursorProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setCursorProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

}
