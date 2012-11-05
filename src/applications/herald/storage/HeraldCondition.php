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
    ) + parent::getConfiguration();
  }

}
