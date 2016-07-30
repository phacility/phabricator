<?php

final class PhabricatorCalendarEventDeclineTransaction
  extends PhabricatorCalendarEventReplyTransaction {

  const TRANSACTIONTYPE = 'calendar.decline';

  public function generateNewValue($object, $value) {
    return PhabricatorCalendarEventInvitee::STATUS_DECLINED;
  }

  public function getTitle() {
    return pht(
      '%s declined this event.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s declined %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}
