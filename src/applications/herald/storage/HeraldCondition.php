<?php

final class HeraldCondition extends HeraldDAO {

  protected $ruleID;

  protected $fieldName;
  protected $fieldCondition;
  protected $value;

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'value' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'fieldName' => 'text255',
        'fieldCondition' => 'text255',
        'value' => 'text',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'ruleID' => array(
          'columns' => array('ruleID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
