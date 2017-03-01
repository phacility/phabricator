<?php

final class PhabricatorCalendarEventTransaction
  extends PhabricatorModularTransaction {

  const MAILTAG_RESCHEDULE = 'calendar-reschedule';
  const MAILTAG_CONTENT = 'calendar-content';
  const MAILTAG_OTHER = 'calendar-other';

  public function getApplicationName() {
    return 'calendar';
  }

  public function getApplicationTransactionType() {
    return PhabricatorCalendarEventPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorCalendarEventTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorCalendarEventTransactionType';
  }

  public function getMailTags() {
    $tags = array();
    switch ($this->getTransactionType()) {
      case PhabricatorCalendarEventNameTransaction::TRANSACTIONTYPE:
      case PhabricatorCalendarEventDescriptionTransaction::TRANSACTIONTYPE:
      case PhabricatorCalendarEventInviteTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_CONTENT;
        break;
      case PhabricatorCalendarEventStartDateTransaction::TRANSACTIONTYPE:
      case PhabricatorCalendarEventEndDateTransaction::TRANSACTIONTYPE:
      case PhabricatorCalendarEventCancelTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_RESCHEDULE;
        break;
    }
    return $tags;
  }

}
