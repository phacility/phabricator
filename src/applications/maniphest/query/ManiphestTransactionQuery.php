<?php

final class ManiphestTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new ManiphestTransaction();
  }

}
