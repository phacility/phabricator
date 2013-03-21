<?php

final class PhluxTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  protected function getTemplateApplicationTransaction() {
    return new PhluxTransaction();
  }

}
