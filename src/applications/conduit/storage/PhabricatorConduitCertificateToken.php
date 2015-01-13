<?php

final class PhabricatorConduitCertificateToken extends PhabricatorConduitDAO {

  protected $userPHID;
  protected $token;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'token' => 'text64?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'userPHID' => array(
          'columns' => array('userPHID'),
          'unique' => true,
        ),
        'token' => array(
          'columns' => array('token'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
