<?php

final class PhabricatorCountdownTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorCountdownTransaction();
  }

}
