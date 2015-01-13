<?php

/**
 * Raw fact about an object.
 */
final class PhabricatorFactRaw extends PhabricatorFactDAO {

  protected $factType;
  protected $objectPHID;
  protected $objectA;
  protected $valueX;
  protected $valueY;
  protected $epoch;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'id' => 'auto64',
        'factType' => 'text32',
        'objectA' => 'phid',
        'valueX' => 'sint64',
        'valueY' => 'sint64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'objectPHID' => array(
          'columns' => array('objectPHID'),
        ),
        'factType' => array(
          'columns' => array('factType', 'epoch'),
        ),
        'factType_2' => array(
          'columns' => array('factType', 'objectA'),
        ),
      ),
    ) + parent::getConfiguration();
  }


}
