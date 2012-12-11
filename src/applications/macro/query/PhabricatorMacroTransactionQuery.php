<?php

final class PhabricatorMacroTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  protected function getTemplateApplicationTransaction() {
    return new PhabricatorMacroTransaction();
  }

}
