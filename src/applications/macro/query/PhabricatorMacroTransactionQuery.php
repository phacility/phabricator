<?php

final class PhabricatorMacroTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorMacroTransaction();
  }

}
