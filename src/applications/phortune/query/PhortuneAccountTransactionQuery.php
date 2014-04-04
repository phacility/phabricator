<?php

final class PhortuneAccountTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhortuneAccountTransaction();
  }

}
