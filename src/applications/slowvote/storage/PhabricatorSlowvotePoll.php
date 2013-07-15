<?php

/**
 * @group slowvote
 */
final class PhabricatorSlowvotePoll extends PhabricatorSlowvoteDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorSubscribableInterface {

  const RESPONSES_VISIBLE = 0;
  const RESPONSES_VOTERS  = 1;
  const RESPONSES_OWNER   = 2;

  const METHOD_PLURALITY  = 0;
  const METHOD_APPROVAL   = 1;

  protected $question;
  protected $description;
  protected $authorPHID;
  protected $responseVisibility;
  protected $shuffle;
  protected $method;
  protected $viewPolicy;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_POLL);
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
        return $this->viewPolicy;
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_NOONE;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return ($viewer->getPHID() == $this->getAuthorPHID());
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($phid == $this->getAuthorPHID());
  }


}
