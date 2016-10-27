<?php

final class PhabricatorCalendarEventInviteTransaction
  extends PhabricatorCalendarEventTransactionType {

  const TRANSACTIONTYPE = 'calendar.invite';

  public function generateOldValue($object) {
    $status_uninvited = PhabricatorCalendarEventInvitee::STATUS_UNINVITED;

    $invitees = $object->getInvitees();
    foreach ($invitees as $key => $invitee) {
      if ($invitee->getStatus() == $status_uninvited) {
        unset($invitees[$key]);
      }
    }

    return array_values(mpull($invitees, 'getInviteePHID'));
  }

  private function generateChangeMap($object, $new_value) {
    $status_invited = PhabricatorCalendarEventInvitee::STATUS_INVITED;
    $status_uninvited = PhabricatorCalendarEventInvitee::STATUS_UNINVITED;
    $status_attending = PhabricatorCalendarEventInvitee::STATUS_ATTENDING;

    $old = $this->generateOldValue($object);

    $add = array_diff($new_value, $old);
    $rem = array_diff($old, $new_value);

    $map = array();
    foreach ($add as $phid) {
      $map[$phid] = $status_invited;
    }

    foreach ($rem as $phid) {
      $map[$phid] = $status_uninvited;
    }

    // If we're creating this event and the actor is inviting themselves,
    // mark them as attending.
    if ($this->isNewObject()) {
      $acting_phid = $this->getActingAsPHID();
      if (isset($map[$acting_phid])) {
        $map[$acting_phid] = $status_attending;
      }
    }

    return $map;
  }

  public function applyExternalEffects($object, $value) {
    $map = $this->generateChangeMap($object, $value);

    $invitees = $object->getInvitees();
    $invitees = mpull($invitees, null, 'getInviteePHID');

    foreach ($map as $phid => $status) {
      $invitee = idx($invitees, $phid);
      if (!$invitee) {
        $invitee = id(new PhabricatorCalendarEventInvitee())
          ->setEventPHID($object->getPHID())
          ->setInviteePHID($phid)
          ->setInviterPHID($this->getActingAsPHID());
        $invitees[] = $invitee;
      }
      $invitee->setStatus($status)
        ->save();
    }

    $object->attachInvitees($invitees);
  }

  public function validateTransactions($object, array $xactions) {
    $actor = $this->getActor();

    $errors = array();

    $old = $object->getInvitees();
    $old = mpull($old, null, 'getInviteePHID');
    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();
      $new = array_fuse($new);
      $add = array_diff_key($new, $old);
      if (!$add) {
        continue;
      }

      // In the UI, we only allow you to invite mailable objects, but there
      // is no definitive marker for "invitable object" today. Just allow
      // any valid object to be invited.
      $objects = id(new PhabricatorObjectQuery())
        ->setViewer($actor)
        ->withPHIDs($add)
        ->execute();
      $objects = mpull($objects, null, 'getPHID');
      foreach ($add as $phid) {
        if (isset($objects[$phid])) {
          continue;
        }

        $errors[] = $this->newInvalidError(
          pht(
            'Invitee "%s" identifies an object that does not exist or '.
            'which you do not have permission to view.',
            $phid),
          $xaction);
      }
    }

    return $errors;
  }

  public function getIcon() {
    return 'fa-user-plus';
  }

  public function getTitle() {
    list($add, $rem) = $this->getChanges();

    if ($add && !$rem) {
      return pht(
        '%s invited %s attendee(s): %s.',
        $this->renderAuthor(),
        phutil_count($add),
        $this->renderHandleList($add));
    } else if (!$add && $rem) {
      return pht(
        '%s uninvited %s attendee(s): %s.',
        $this->renderAuthor(),
        phutil_count($rem),
        $this->renderHandleList($rem));
    } else {
      return pht(
        '%s invited %s attendee(s): %s; uninvinted %s attendee(s): %s.',
        $this->renderAuthor(),
        phutil_count($add),
        $this->renderHandleList($add),
        phutil_count($rem),
        $this->renderHandleList($rem));
    }
  }

  public function getTitleForFeed() {
    list($add, $rem) = $this->getChanges();

    if ($add && !$rem) {
      return pht(
        '%s invited %s attendee(s) to %s: %s.',
        $this->renderAuthor(),
        phutil_count($add),
        $this->renderObject(),
        $this->renderHandleList($add));
    } else if (!$add && $rem) {
      return pht(
        '%s uninvited %s attendee(s) to %s: %s.',
        $this->renderAuthor(),
        phutil_count($rem),
        $this->renderObject(),
        $this->renderHandleList($rem));
    } else {
      return pht(
        '%s updated the invite list for %s, invited %s: %s; '.
        'uninvinted %s: %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        phutil_count($add),
        $this->renderHandleList($add),
        phutil_count($rem),
        $this->renderHandleList($rem));
    }
  }

  private function getChanges() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $add = array_diff($new, $old);
    $rem = array_diff($old, $new);

    return array(array_fuse($add), array_fuse($rem));
  }

}
