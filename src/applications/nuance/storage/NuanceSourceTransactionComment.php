<?php

final class NuanceSourceTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new NuanceSourceTransaction();
  }

}
