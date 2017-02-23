<?php

final class PhabricatorXHProfSample
  extends PhabricatorXHProfDAO
  implements PhabricatorPolicyInterface {

  protected $filePHID;
  protected $usTotal;
  protected $sampleRate;
  protected $hostname;
  protected $requestPath;
  protected $controller;
  protected $userPHID;

  public static function initializeNewSample() {
    return id(new self())
      ->setUsTotal(0)
      ->setSampleRate(0);
  }

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

  public function getURI() {
    return '/xhprof/profile/'.$this->getFilePHID().'/';
  }

  public function getDisplayName() {
    $request_path = $this->getRequestPath();
    if (strlen($request_path)) {
      return $request_path;
    }

    return pht('Unnamed Sample');
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::getMostOpenPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
