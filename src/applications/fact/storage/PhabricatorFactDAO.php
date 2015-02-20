<?php

abstract class PhabricatorFactDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'fact';
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

}
