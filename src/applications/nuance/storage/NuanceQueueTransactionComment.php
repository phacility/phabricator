<?php

final class NuanceQueueTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new NuanceQueueTransaction();
  }

}
