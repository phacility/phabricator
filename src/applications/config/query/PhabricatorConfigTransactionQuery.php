<?php

final class PhabricatorConfigTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  protected function getTemplateApplicationTransaction() {
    return new PhabricatorConfigTransaction();
  }

}
