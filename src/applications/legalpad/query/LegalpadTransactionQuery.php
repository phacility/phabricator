<?php

/**
 * @group legalpad
 */
final class LegalpadTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  protected function getTemplateApplicationTransaction() {
    return new LegalpadTransaction();
  }

}
