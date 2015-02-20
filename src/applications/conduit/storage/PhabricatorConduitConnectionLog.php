<?php

final class PhabricatorConduitConnectionLog extends PhabricatorConduitDAO {

  protected $client;
  protected $clientVersion;
  protected $clientDescription;
  protected $username;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'client' => 'text255?',
        'clientVersion' => 'text255?',
        'clientDescription' => 'text255?',
        'username' => 'text255?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_created' => array(
          'columns' => array('dateCreated'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
