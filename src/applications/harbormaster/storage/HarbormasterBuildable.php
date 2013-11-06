<?php

final class HarbormasterBuildable extends HarbormasterDAO
  implements PhabricatorPolicyInterface {

  protected $buildablePHID;
  protected $containerPHID;
  protected $buildStatus;
  protected $buildableStatus;

  private $buildableObject = self::ATTACHABLE;
  private $containerObject = self::ATTACHABLE;
  private $buildableHandle = self::ATTACHABLE;

  const STATUS_WHATEVER = 'whatever';

  public static function initializeNewBuildable(PhabricatorUser $actor) {
    return id(new HarbormasterBuildable())
      ->setBuildStatus(self::STATUS_WHATEVER)
      ->setBuildableStatus(self::STATUS_WHATEVER);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      HarbormasterPHIDTypeBuildable::TYPECONST);
  }

  public function attachBuildableObject($buildable_object) {
    $this->buildableObject = $buildable_object;
    return $this;
  }

  public function getBuildableObject() {
    return $this->assertAttached($this->buildableObject);
  }

  public function attachContainerObject($container_object) {
    $this->containerObject = $container_object;
    return $this;
  }

  public function getContainerObject() {
    return $this->assertAttached($this->containerObject);
  }

  public function attachBuildableHandle($buildable_handle) {
    $this->buildableHandle = $buildable_handle;
    return $this;
  }

  public function getBuildableHandle() {
    return $this->assertAttached($this->buildableHandle);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->getBuildableObject()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getBuildableObject()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'Users must be able to see the revision or repository to see a '.
      'buildable.');
  }

}
