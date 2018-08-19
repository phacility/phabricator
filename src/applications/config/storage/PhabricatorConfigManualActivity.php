<?php

final class PhabricatorConfigManualActivity
  extends PhabricatorConfigEntryDAO {

  protected $activityType;
  protected $parameters = array();

  const TYPE_REINDEX = 'reindex';
  const TYPE_IDENTITIES = 'identities';


  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'parameters' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'activityType' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_type' => array(
          'columns' => array('activityType'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function setParameter($key, $value) {
    $this->parameters[$key] = $value;
    return $this;
  }

  public function getParameter($key, $default = null) {
    return idx($this->parameters, $key, $default);
  }

}
