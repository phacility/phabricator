<?php

/**
 * A collection of dashboard panels with a specific layout.
 */
final class PhabricatorDashboard extends PhabricatorDashboardDAO
  implements PhabricatorPolicyInterface {

  protected $name;
  protected $viewPolicy;
  protected $editPolicy;

  public static function initializeNewDashboard(PhabricatorUser $actor) {
    return id(new PhabricatorDashboard())
      ->setName('')
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->setEditPolicy($actor->getPHID());
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorDashboardPHIDTypeDashboard::TYPECONST);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


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
