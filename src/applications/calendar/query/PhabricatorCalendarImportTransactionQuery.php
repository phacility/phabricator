<?php

final class PhabricatorCalendarImportTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorCalendarImportTransaction();
  }

}
