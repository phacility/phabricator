<?php

final class PhragmentSnapshotChild extends PhragmentDAO
  implements PhabricatorPolicyInterface {

  protected $snapshotPHID;
  protected $fragmentPHID;
  protected $fragmentVersionPHID;

  private $snapshot = self::ATTACHABLE;
  private $fragment = self::ATTACHABLE;
  private $fragmentVersion = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'fragmentVersionPHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_child' => array(
          'columns' => array(
            'snapshotPHID',
            'fragmentPHID',
            'fragmentVersionPHID',
          ),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getSnapshot() {
    return $this->assertAttached($this->snapshot);
  }

  public function attachSnapshot(PhragmentSnapshot $snapshot) {
    return $this->snapshot = $snapshot;
  }

  public function getFragment() {
    return $this->assertAttached($this->fragment);
  }

  public function attachFragment(PhragmentFragment $fragment) {
    return $this->fragment = $fragment;
  }

  public function getFragmentVersion() {
    if ($this->fragmentVersionPHID === null) {
      return null;
    }
    return $this->assertAttached($this->fragmentVersion);
  }

  public function attachFragmentVersion(PhragmentFragmentVersion $version) {
    return $this->fragmentVersion = $version;
  }


/* -(  Policy Interface  )--------------------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->getSnapshot()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getSnapshot()
      ->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return $this->getSnapshot()
      ->describeAutomaticCapability($capability);
  }
}
