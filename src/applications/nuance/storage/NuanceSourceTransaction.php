<?php

final class NuanceSourceTransaction
  extends NuanceTransaction {

  public function getApplicationTransactionType() {
    return NuanceSourcePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new NuanceSourceTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'NuanceSourceTransactionType';
  }

}
