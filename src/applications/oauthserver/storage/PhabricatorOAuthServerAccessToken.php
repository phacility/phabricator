<?php

final class PhabricatorOAuthServerAccessToken
  extends PhabricatorOAuthServerDAO {

  protected $id;
  protected $token;
  protected $userPHID;
  protected $clientPHID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'token' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'token' => array(
          'columns' => array('token'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
