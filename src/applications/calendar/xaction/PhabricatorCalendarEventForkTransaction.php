<?php

final class PhabricatorCalendarEventForkTransaction
  extends PhabricatorCalendarEventTransactionType {

  const TRANSACTIONTYPE = 'calendar.fork';

  public function generateOldValue($object) {
    return false;
  }

  public function shouldHide() {
    // This transaction is purely an internal implementation detail which
    // supports editing groups of events like "All Future Events".
    return true;
  }

  public function applyInternalEffects($object, $value) {
    $parent = $object->getParentEvent();

    $object->setInstanceOfEventPHID(null);
    $object->attachParentEvent(null);

    $rrule = $parent->newRecurrenceRule();
    $object->setRecurrenceRule($rrule);

    $until = $parent->newUntilDateTime();
    if ($until) {
      $object->setUntilDateTime($until);
    }

    $old_sequence_index = $object->getSequenceIndex();
    $object->setSequenceIndex(0);

    // Stop the parent event from recurring after the start date of this event.
    // Since the "until" time is inclusive, rewind it by one second. We could
    // figure out the previous instance's time instead or use a COUNT, but this
    // seems simpler as long as it doesn't cause any issues.
    $until_cutoff = $object->newStartDateTime()
      ->newRelativeDateTime('-PT1S')
      ->newAbsoluteDateTime();

    $parent->setUntilDateTime($until_cutoff);
    $parent->save();

    // NOTE: If we implement "COUNT" on editable events, we need to adjust
    // the "COUNT" here and divide it up between the parent and the fork.

    // Make all following children of the old parent children of this node
    // instead.
    $conn = $object->establishConnection('w');
    queryfx(
      $conn,
      'UPDATE %T SET
        instanceOfEventPHID = %s,
        sequenceIndex = (sequenceIndex - %d)
        WHERE instanceOfEventPHID = %s
        AND utcInstanceEpoch > %d',
      $object->getTableName(),
      $object->getPHID(),
      $old_sequence_index,
      $parent->getPHID(),
      $object->getUTCInstanceEpoch());
  }

}
