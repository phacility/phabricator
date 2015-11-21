<?php

final class PhameBlogTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhameBlogTransaction();
  }

}
