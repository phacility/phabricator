<?php

abstract class PhabricatorCustomFieldStorage
  extends PhabricatorLiskDAO {

  protected $objectPHID;
  protected $fieldIndex;
  protected $fieldValue;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

}
