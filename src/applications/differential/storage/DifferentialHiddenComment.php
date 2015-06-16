<?php

final class DifferentialHiddenComment
  extends DifferentialDAO {

  protected $userPHID;
  protected $commentID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_KEY_SCHEMA => array(
        'key_user' => array(
          'columns' => array('userPHID', 'commentID'),
          'unique' => true,
        ),
        'key_comment' => array(
          'columns' => array('commentID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
