<?php

final class PhabricatorCalendarImportTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'calendar';
  }

  public function getApplicationTransactionType() {
    return PhabricatorCalendarImportPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorCalendarImportTransactionType';
  }

}
