<?php

final class PhabricatorProjectColumn
  extends PhabricatorProjectDAO
  implements PhabricatorPolicyInterface {

  const STATUS_ACTIVE = 0;
  const STATUS_DELETED = 1;

  protected $name;
  protected $status;
  protected $projectPHID;
  protected $sequence;

  private $project = self::ATTACHABLE;

  public static function initializeNewColumn(PhabricatorUser $user) {
    return id(new PhabricatorProjectColumn())
      ->setName('')
      ->setStatus(self::STATUS_ACTIVE);
  }

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

  public function isDefaultColumn() {
    return ($this->getSequence() == 0);
  }

  public function isDeleted() {
    return ($this->getStatus() == self::STATUS_DELETED);
  }

  public function getDisplayName() {
    if ($this->isDefaultColumn()) {
      return pht('Backlog');
    }
    return $this->getName();
  }

  public function getHeaderColor() {
    if ($this->isDefaultColumn()) {
      return PhabricatorActionHeaderView::HEADER_DARK_GREY;
    }
    return PhabricatorActionHeaderView::HEADER_GREY;
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
