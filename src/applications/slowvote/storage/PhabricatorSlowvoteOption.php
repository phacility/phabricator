<?php

final class PhabricatorSlowvoteOption extends PhabricatorSlowvoteDAO {

  protected $pollID;
  protected $name;

  public function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
      ),
    ) + parent::getConfiguration();
  }

}
