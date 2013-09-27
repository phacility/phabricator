<?php

/**
 * @group countdown
 */
final class PhabricatorCountdown
  extends PhabricatorCountdownDAO
  implements PhabricatorPolicyInterface {

  protected $id;
  protected $phid;
  protected $title;
  protected $authorPHID;
  protected $epoch;
  // protected $viewPolicy; //commented out till we have it on countdown table

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorCountdownPHIDTypeCountdown::TYPECONST);
  }

  public function getViewPolicy() {
    return PhabricatorPolicies::POLICY_USER;
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
        return PhabricatorPolicies::POLICY_NOONE;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return ($viewer->getPHID() == $this->getAuthorPHID());
  }

  public function describeAutomaticCapability($capability) {
    return pht('The author of a countdown can always view and edit it.');
  }

}
