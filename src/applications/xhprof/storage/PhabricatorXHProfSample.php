<?php

final class PhabricatorXHProfSample extends PhabricatorXHProfDAO {

  protected $filePHID;
  protected $usTotal;
  protected $sampleRate;
  protected $hostname;
  protected $requestPath;
  protected $controller;
  protected $userPHID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'sampleRate' => 'uint32',
        'usTotal' => 'uint64',
        'hostname' => 'text255?',
        'requestPath' => 'text255?',
        'controller' => 'text255?',
        'userPHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'filePHID' => array(
          'columns' => array('filePHID'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
