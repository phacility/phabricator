<?php

final class NuanceRequestorSource
  extends NuanceDAO {

  protected $requestorPHID;
  protected $sourcePHID;
  protected $sourceKey;
  protected $data;

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'data' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'sourceKey' => 'text128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_source_key' => array(
          'columns' => array('sourcePHID', 'sourceKey'),
          'unique' => true,
        ),
        'key_requestor' => array(
          'columns' => array('requestorPHID', 'id'),
        ),
        'key_source' => array(
          'columns' => array('sourcePHID', 'id'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
