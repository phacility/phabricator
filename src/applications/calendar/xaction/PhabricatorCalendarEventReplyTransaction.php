<?php

abstract class PhabricatorCalendarEventReplyTransaction
  extends PhabricatorCalendarEventTransactionType {

  public function generateOldValue($object) {
    $actor_phid = $this->getActingAsPHID();
    return $object->getUserInviteStatus($actor_phid);
  }

  public function isInheritedEdit() {
    return false;
  }

  public function applyExternalEffects($object, $value) {
    $acting_phid = $this->getActingAsPHID();

    $invitees = $object->getInvitees();
    $invitees = mpull($invitees, null, 'getInviteePHID');

    $invitee = idx($invitees, $acting_phid);
    if (!$invitee) {
      $invitee = id(new PhabricatorCalendarEventInvitee())
        ->setEventPHID($object->getPHID())
        ->setInviteePHID($acting_phid)
        ->setInviterPHID($acting_phid);
      $invitees[$acting_phid] = $invitee;
    }

    $invitee
      ->setStatus($value)
      ->save();

    $object->attachInvitees($invitees);
  }

}
