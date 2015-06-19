<?php

final class PhabricatorMetaMTAApplicationEmailTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorMetaMTAApplicationEmailTransaction();
  }

}
