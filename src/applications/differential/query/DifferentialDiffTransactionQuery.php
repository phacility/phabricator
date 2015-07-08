<?php

final class DifferentialDiffTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new DifferentialDiffTransaction();
  }

}
