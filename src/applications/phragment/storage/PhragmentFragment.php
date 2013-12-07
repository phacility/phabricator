<?php

final class PhragmentFragment extends PhragmentDAO
  implements PhabricatorPolicyInterface {

  protected $path;
  protected $depth;
  protected $latestVersionPHID;
  protected $viewPolicy;
  protected $editPolicy;

  private $latestVersion = self::ATTACHABLE;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhragmentPHIDTypeFragment::TYPECONST);
  }

  public function getURI() {
    return '/phragment/fragment/'.$this->getID().'/';
  }

  public function getName() {
    return basename($this->path);
  }

  public function getFile() {
    return $this->assertAttached($this->file);
  }

  public function attachFile(PhabricatorFile $file) {
    return $this->file = $file;
  }

  public function getLatestVersion() {
    return $this->assertAttached($this->latestVersion);
  }

  public function attachLatestVersion(PhragmentFragmentVersion $version) {
    return $this->latestVersion = $version;
  }

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }

}
