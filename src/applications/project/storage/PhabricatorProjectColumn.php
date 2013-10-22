<?php

final class PhabricatorProjectColumn
  extends PhabricatorProjectDAO
  implements PhabricatorPolicyInterface {

  protected $name;
  protected $projectPHID;
  protected $sequence;

  private $project = self::ATTACHABLE;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorProjectPHIDTypeColumn::TYPECONST);
  }

  public function attachProject(PhabricatorProject $project) {
    $this->project = $project;
    return $this;
  }

  public function getProject() {
    return $this->assertAttached($this->project);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getProject()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getProject()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('Users must be able to see a project to see its board.');
  }

}
