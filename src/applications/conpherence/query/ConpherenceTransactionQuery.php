<?php

/**
 * @group conpherence
 */
final class ConpherenceTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new ConpherenceTransaction();
  }

  protected function getReversePaging() {
    return false;
  }


}
