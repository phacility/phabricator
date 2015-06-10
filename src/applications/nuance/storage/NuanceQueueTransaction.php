<?php

final class NuanceQueueTransaction extends NuanceTransaction {

  const TYPE_NAME = 'nuance.queue.name';

  public function getApplicationTransactionType() {
    return NuanceQueuePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new NuanceQueueTransactionComment();
  }

}
