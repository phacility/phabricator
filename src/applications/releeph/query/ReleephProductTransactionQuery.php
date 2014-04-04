<?php

final class ReleephProductTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new ReleephProductTransaction();
  }

}
