<?php

final class PhabricatorTransformedFile extends PhabricatorFileDAO {

  protected $originalPHID;
  protected $transform;
  protected $transformedPHID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'transform' => 'text128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'originalPHID' => array(
          'columns' => array('originalPHID', 'transform'),
          'unique' => true,
        ),
        'transformedPHID' => array(
          'columns' => array('transformedPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
