<?php

final class NuanceQueueTransaction extends NuanceTransaction {

  public function getApplicationTransactionType() {
    return NuanceQueuePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new NuanceQueueTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'NuanceQueueTransactionType';
  }

}
