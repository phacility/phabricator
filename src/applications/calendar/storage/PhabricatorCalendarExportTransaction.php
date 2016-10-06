<?php

final class PhabricatorCalendarExportTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'calendar';
  }

  public function getApplicationTransactionType() {
    return PhabricatorCalendarExportPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorCalendarExportTransactionType';
  }

}
