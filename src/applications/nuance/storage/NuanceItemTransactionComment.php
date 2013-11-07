<?php

final class NuanceItemTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new NuanceItemTransaction();
  }

}
