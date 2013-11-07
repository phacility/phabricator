<?php

final class NuanceRequestorTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new NuanceRequestorTransaction();
  }

}
