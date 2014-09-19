<?php

final class PhabricatorConduitCertificateToken extends PhabricatorConduitDAO {

  protected $userPHID;
  protected $token;

  public function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'token' => 'text64?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'userPHID' => array(
          'columns' => array('userPHID'),
        ),
        'token' => array(
          'columns' => array('token'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
