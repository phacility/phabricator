<?php

final class PhabricatorRoleTriggerTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorRoleTriggerTransaction();
  }

}
