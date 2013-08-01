<?php

/**
 * @group legalpad
 */
final class LegalpadTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new LegalpadTransaction();
  }

}
