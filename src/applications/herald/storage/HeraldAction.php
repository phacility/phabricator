<?php

final class HeraldAction extends HeraldDAO {

  protected $ruleID;

  protected $action;
  protected $target;

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'target' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'action' => 'text255',
        'target' => 'text',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'ruleID' => array(
          'columns' => array('ruleID'),
        ),
      ),
    ) + parent::getConfiguration();
  }


}
