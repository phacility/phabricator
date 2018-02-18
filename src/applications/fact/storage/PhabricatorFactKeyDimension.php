<?php

final class PhabricatorFactKeyDimension
  extends PhabricatorFactDimension {

  protected $factKey;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'factKey' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_factkey' => array(
          'columns' => array('factKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  protected function getDimensionColumnName() {
    return 'factKey';
  }

}
