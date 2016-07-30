<?php

final class PhabricatorCalendarEventInviteesPolicyRule
  extends PhabricatorPolicyRule {

  private $invited = array();
  private $sourcePHIDs = array();

  public function getObjectPolicyKey() {
    return 'calendar.event.invitees';
  }

  public function getObjectPolicyName() {
    return pht('Event Invitees');
  }

  public function getPolicyExplanation() {
    return pht('Users invited to this event can take this action.');
  }

  public function getRuleDescription() {
    return pht('event invitees');
  }

  public function canApplyToObject(PhabricatorPolicyInterface $object) {
    return ($object instanceof PhabricatorCalendarEvent);
  }

  public function willApplyRules(
    PhabricatorUser $viewer,
    array $values,
    array $objects) {

    $viewer_phid = $viewer->getPHID();
    if (!$viewer_phid) {
      return;
    }

    if (empty($this->invited[$viewer_phid])) {
      $this->invited[$viewer_phid] = array();
    }

    if (!isset($this->sourcePHIDs[$viewer_phid])) {
      $source_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $viewer_phid,
        PhabricatorProjectMemberOfProjectEdgeType::EDGECONST);
      $source_phids[] = $viewer_phid;
      $this->sourcePHIDs[$viewer_phid] = $source_phids;
    }

    foreach ($objects as $key => $object) {
      $cache = $this->getTransactionHint($object);
      if ($cache === null) {
        // We don't have a hint for this object, so we'll deal with it below.
        continue;
      }

      // We have a hint, so use that as the source of truth.
      unset($objects[$key]);

      foreach ($this->sourcePHIDs[$viewer_phid] as $source_phid) {
        if (isset($cache[$source_phid])) {
          $this->invited[$viewer_phid][$object->getPHID()] = true;
          break;
        }
      }
    }

    $phids = mpull($objects, 'getPHID');
    if (!$phids) {
      return;
    }

    $invited = id(new PhabricatorCalendarEventInvitee())->loadAllWhere(
      'eventPHID IN (%Ls)
        AND inviteePHID IN (%Ls)
        AND status != %s',
      $phids,
      $this->sourcePHIDs[$viewer_phid],
      PhabricatorCalendarEventInvitee::STATUS_UNINVITED);
    $invited = mpull($invited, 'getEventPHID');

    $this->invited[$viewer_phid] += array_fill_keys($invited, true);
  }

  public function applyRule(
    PhabricatorUser $viewer,
    $value,
    PhabricatorPolicyInterface $object) {

    $viewer_phid = $viewer->getPHID();
    if (!$viewer_phid) {
      return false;
    }

    $invited = idx($this->invited, $viewer_phid);
    return isset($invited[$object->getPHID()]);
  }

  public function getValueControlType() {
    return self::CONTROL_TYPE_NONE;
  }

}
