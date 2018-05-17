<?php

final class PhabricatorMutedByEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 68;

  public function getInverseEdgeConstant() {
    return PhabricatorMutedEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
