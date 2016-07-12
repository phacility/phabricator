<?php

final class PhortuneCartTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhortuneCartTransaction();
  }

}
