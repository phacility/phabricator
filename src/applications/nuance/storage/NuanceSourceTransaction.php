<?php

final class NuanceSourceTransaction
  extends NuanceTransaction {

  public function getApplicationTransactionType() {
    return NuancePHIDTypeSource::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new NuanceSourceTransactionComment();
  }

}
