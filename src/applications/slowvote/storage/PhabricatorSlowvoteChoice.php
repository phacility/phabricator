<?php

final class PhabricatorSlowvoteChoice extends PhabricatorSlowvoteDAO {

  protected $pollID;
  protected $optionID;
  protected $authorPHID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_KEY_SCHEMA => array(
        'pollID' => array(
          'columns' => array('pollID'),
        ),
        'authorPHID' => array(
          'columns' => array('authorPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
