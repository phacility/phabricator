<?php

final class PhabricatorCalendarEventHostPolicyRule
  extends PhabricatorPolicyRule {

  public function getObjectPolicyKey() {
    return 'calendar.event.host';
  }

  public function getObjectPolicyName() {
    return pht('Event Host');
  }

  public function getPolicyExplanation() {
    return pht('The host of this event can take this action.');
  }

  public function getRuleDescription() {
    return pht('event host');
  }

  public function canApplyToObject(PhabricatorPolicyInterface $object) {
    return ($object instanceof PhabricatorCalendarEvent);
  }

  public function applyRule(
    PhabricatorUser $viewer,
    $value,
    PhabricatorPolicyInterface $object) {

    $viewer_phid = $viewer->getPHID();
    if (!$viewer_phid) {
      return false;
    }

    return ($object->getHostPHID() == $viewer_phid);
  }

  public function getValueControlType() {
    return self::CONTROL_TYPE_NONE;
  }

}
