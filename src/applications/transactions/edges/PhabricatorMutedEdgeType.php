<?php

final class PhabricatorMutedEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 67;

  public function getInverseEdgeConstant() {
    return PhabricatorMutedByEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
