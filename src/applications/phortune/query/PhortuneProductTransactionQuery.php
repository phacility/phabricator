<?php

final class PhortuneProductTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhortuneProductTransaction();
  }

}
