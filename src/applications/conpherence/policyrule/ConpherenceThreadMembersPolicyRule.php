<?php

final class ConpherenceThreadMembersPolicyRule
  extends PhabricatorPolicyRule {

  public function getObjectPolicyKey() {
    return 'conpherence.members';
  }

  public function getObjectPolicyName() {
    return pht('Room Participants');
  }

  public function getPolicyExplanation() {
    return pht('Participants in this room can take this action.');
  }

  public function getRuleDescription() {
    return pht('room participants');
  }

  public function getObjectPolicyIcon() {
    return 'fa-comments';
  }

  public function canApplyToObject(PhabricatorPolicyInterface $object) {
    return ($object instanceof ConpherenceThread);
  }

  public function applyRule(
    PhabricatorUser $viewer,
    $value,
    PhabricatorPolicyInterface $object) {
    $viewer_phid = $viewer->getPHID();
    if (!$viewer_phid) {
      return false;
    }

    return (bool)$object->getParticipantIfExists($viewer_phid);
  }

  public function getValueControlType() {
    return self::CONTROL_TYPE_NONE;
  }

}
