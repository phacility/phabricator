<?php

final class PhabricatorObjectUsesCredentialsEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 39;

  public function getInverseEdgeConstant() {
    return PhabricatorCredentialsUsedByObjectEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
