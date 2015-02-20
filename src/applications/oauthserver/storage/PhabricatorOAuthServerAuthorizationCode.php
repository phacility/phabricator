<?php

final class PhabricatorOAuthServerAuthorizationCode
  extends PhabricatorOAuthServerDAO {

  protected $id;
  protected $code;
  protected $clientPHID;
  protected $clientSecret;
  protected $userPHID;
  protected $redirectURI;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'code' => 'text32',
        'clientSecret' => 'text32',
        'redirectURI' => 'text255',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'code' => array(
          'columns' => array('code'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
