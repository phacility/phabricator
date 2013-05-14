<?php

final class ReleephRequestTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  protected function getTemplateApplicationTransaction() {
    return new ReleephRequestTransaction();
  }

}
