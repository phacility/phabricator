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
    ) + parent::getConfiguration();
  }


}
