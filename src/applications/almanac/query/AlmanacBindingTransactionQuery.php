<?php

final class AlmanacBindingTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new AlmanacBindingTransaction();
  }

}
