<?php

final class PhabricatorUserProfile extends PhabricatorUserDAO {

  protected $userPHID;
  protected $title;
  protected $blurb;
  protected $profileImagePHID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'title' => 'text255',
        'blurb' => 'text',
        'profileImagePHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'userPHID' => array(
          'columns' => array('userPHID'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
