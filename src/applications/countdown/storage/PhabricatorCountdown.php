<?php

/**
 * @group countdown
 */
final class PhabricatorCountdown extends PhabricatorCountdownDAO
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
    return PhabricatorPHID::generateNewPHID('CDWN');
  }

  public function getViewPolicy() {
    return "users";
  }

/* -(  PhabricatorPolicyInterface Implementation  )------------------------- */

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return ($viewer->getPHID() == $this->getAuthorPHID());
  }

}
