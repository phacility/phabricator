<?php

/**
 * @group file
 */
final class PhabricatorFileTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorFileTransaction();
  }

}
