<?php

final class NuanceRequestorTransaction
  extends NuanceTransaction {

  public function getApplicationTransactionType() {
    return NuanceRequestorPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new NuanceRequestorTransactionComment();
  }
}
