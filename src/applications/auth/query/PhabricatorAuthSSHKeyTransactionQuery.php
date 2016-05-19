<?php

final class PhabricatorAuthSSHKeyTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorAuthSSHKeyTransaction();
  }

}
