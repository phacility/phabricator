<?php

final class PhabricatorCalendarEventTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorCalendarEventTransaction();
  }

}
