<?php

final class PhabricatorCalendarEventAcceptTransaction
  extends PhabricatorCalendarEventReplyTransaction {

  const TRANSACTIONTYPE = 'calendar.accept';

  public function generateNewValue($object, $value) {
    return PhabricatorCalendarEventInvitee::STATUS_ATTENDING;
  }

  public function getTitle() {
    return pht(
      '%s is attending this event.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s is attending %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}
