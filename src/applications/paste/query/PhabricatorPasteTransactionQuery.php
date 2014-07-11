<?php

final class PhabricatorPasteTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorPasteTransaction();
  }

}
