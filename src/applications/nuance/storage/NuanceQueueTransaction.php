<?php

final class NuanceQueueTransaction
  extends NuanceTransaction {

  public function getApplicationTransactionType() {
    return NuancePHIDTypeQueue::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new NuanceQueueTransactionComment();
  }

}
