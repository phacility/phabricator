<?php

final class PhabricatorSlowvoteTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  protected function getTemplateApplicationTransaction() {
    return new PhabricatorSlowvoteTransaction();
  }

}
