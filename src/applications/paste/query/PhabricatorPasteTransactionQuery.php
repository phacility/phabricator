<?php

/**
 * @group paste
 */
final class PhabricatorPasteTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorPasteTransaction();
  }

}
