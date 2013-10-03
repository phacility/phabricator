<?php

/**
 * @group phrequent
 */
final class PhrequentUserTime extends PhrequentDAO
  implements PhabricatorPolicyInterface {

  protected $userPHID;
  protected $objectPHID;
  protected $note;
  protected $dateStarted;
  protected $dateEnded;

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    $policy = PhabricatorPolicies::POLICY_NOONE;

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        $policy = PhabricatorPolicies::POLICY_USER;
        break;
    }

    return $policy;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return ($viewer->getPHID() == $this->getUserPHID());
  }


  public function describeAutomaticCapability($capability) {
    return pht(
      'The user who tracked time can always view it.');
  }

}
