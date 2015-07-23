<?php

final class PhabricatorCalendarEventTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new PhabricatorCalendarEventTransaction();
  }

}
