<?php

final class PhortuneAccountEmailTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhortuneAccountEmailTransaction();
  }

}
