<?php

abstract class PhabricatorCalendarEventDateTransaction
  extends PhabricatorCalendarEventTransactionType {

  abstract protected function getInvalidDateMessage();

  public function isInheritedEdit() {
    return false;
  }

  public function generateNewValue($object, $value) {
    $editor = $this->getEditor();

    if ($value->isDisabled()) {
      return null;
    }

    return $value->newPhutilDateTime()
      ->setIsAllDay($editor->getNewIsAllDay())
      ->newAbsoluteDateTime()
      ->toDictionary();
  }

  public function getTransactionHasEffect($object, $old, $new) {
    // If either value is `null` (for example, when setting a recurring event
    // end date for the first time) and the other value is not `null`, this
    // transaction has an effect.
    $has_null = (($old === null) || ($new === null));
    if ($has_null) {
      return ($old !== $new);
    }

    $editor = $this->getEditor();

    $actor = $this->getActor();
    $actor_timezone = $actor->getTimezoneIdentifier();

    // When an edit only changes the timezone of an event without materially
    // changing the absolute time, discard it. This can happen if two users in
    // different timezones edit an event without rescheduling it.

    // Eventually, after T11073, there may be a UI control to adjust timezones.
    // If a user explicitly changed the timezone, we should respect that.
    // However, there is no way for users to intentionally apply this kind of
    // edit today.

    $old_datetime = PhutilCalendarAbsoluteDateTime::newFromDictionary($old)
      ->setIsAllDay($editor->getNewIsAllDay())
      ->setViewerTimezone($actor_timezone);

    $new_datetime = PhutilCalendarAbsoluteDateTime::newFromDictionary($new)
      ->setIsAllDay($editor->getNewIsAllDay())
      ->setViewerTimezone($actor_timezone);

    $old_epoch = $old_datetime->getEpoch();
    $new_epoch = $new_datetime->getEpoch();

    return ($old_epoch !== $new_epoch);
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      if ($xaction->getNewValue()->isValid()) {
        continue;
      }

      $message = $this->getInvalidDateMessage();
      $errors[] = $this->newInvalidError($message, $xaction);
    }

    return $errors;
  }

}
