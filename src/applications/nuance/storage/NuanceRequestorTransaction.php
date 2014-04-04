<?php

final class NuanceRequestorTransaction
  extends NuanceTransaction {

  public function getApplicationTransactionType() {
    return NuancePHIDTypeRequestor::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new NuanceRequestorTransactionComment();
  }
}
