<?php

final class PhabricatorCalendarExportTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorCalendarExportTransaction();
  }

}
