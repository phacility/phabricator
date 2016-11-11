<?php

abstract class PhabricatorCalendarEventTransactionType
  extends PhabricatorModularTransactionType {

  public function isInheritedEdit() {
    return true;
  }

}
