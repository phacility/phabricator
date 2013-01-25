<?php

/**
 * @group conpherence
 */
final class ConpherenceTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  protected function getTemplateApplicationTransaction() {
    return new ConpherenceTransaction();
  }

}
