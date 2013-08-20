<?php

final class ReleephBranchTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new ReleephBranchTransaction();
  }

}
