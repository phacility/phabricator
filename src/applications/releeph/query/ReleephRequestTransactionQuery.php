<?php

final class ReleephRequestTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new ReleephRequestTransaction();
  }

}
