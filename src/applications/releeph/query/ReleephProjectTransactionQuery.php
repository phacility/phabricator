<?php

final class ReleephProjectTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new ReleephProjectTransaction();
  }

}
