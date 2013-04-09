<?php

final class PhortuneAccountTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  protected function getTemplateApplicationTransaction() {
    return new PhortuneAccountTransaction();
  }

}
