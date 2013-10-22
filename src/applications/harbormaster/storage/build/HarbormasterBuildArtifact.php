<?php

final class HarbormasterBuildArtifact extends HarbormasterDAO
  implements PhabricatorPolicyInterface {

  protected $buildablePHID;
  protected $artifactType;
  protected $artifactIndex;
  protected $artifactKey;
  protected $artifactData = array();

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'artifactData' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function attachBuildable(HarbormasterBuildable $buildable) {
    $this->buildable = $buildable;
    return $this;
  }

  public function getBuildable() {
    return $this->assertAttached($this->buildable);
  }

  public function setArtifactKey($key) {
    $this->artifactIndex = PhabricatorHash::digestForIndex($key);
    $this->artifactKey = $key;
    return $this;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->getBuildable()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getBuildable()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'Users must be able to see a buildable to see its artifacts.');
  }

}
