<?php

/**
 * @group pholio
 */
final class PholioTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  protected function getTemplateApplicationTransaction() {
    return new PholioTransaction();
  }

}
