<?php

final class PhortuneProductTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  protected function getTemplateApplicationTransaction() {
    return new PhortuneProductTransaction();
  }

}
