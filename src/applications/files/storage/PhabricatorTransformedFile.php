<?php

final class PhabricatorTransformedFile extends PhabricatorFileDAO {

  protected $originalPHID;
  protected $transform;
  protected $transformedPHID;

  public function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'transform' => 'text255',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'originalPHID' => array(
          'columns' => array('originalPHID', 'transform'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
