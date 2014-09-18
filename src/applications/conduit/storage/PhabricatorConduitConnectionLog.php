<?php

final class PhabricatorConduitConnectionLog extends PhabricatorConduitDAO {

  protected $client;
  protected $clientVersion;
  protected $clientDescription;
  protected $username;

  public function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'client' => 'text255?',
        'clientVersion' => 'text255?',
        'clientDescription' => 'text255?',
        'username' => 'text255?',
      ),
    ) + parent::getConfiguration();
  }

}
