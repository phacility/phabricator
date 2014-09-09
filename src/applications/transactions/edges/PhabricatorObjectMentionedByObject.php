<?php

final class PhabricatorObjectMentionedByObject extends PhabricatorEdgeType {

  const EDGECONST = 51;

  public function getInverseEdgeConstant() {
    return PhabricatorObjectMentionsObject::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

  public function getTransactionAddString(
    $actor,
    $add_count,
    $add_edges) {

    return pht(
      '%s mentioned this in %s.',
      $actor,
      $add_edges);
  }

}
