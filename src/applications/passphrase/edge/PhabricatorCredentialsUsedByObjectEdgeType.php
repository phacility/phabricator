<?php

final class PhabricatorCredentialsUsedByObjectEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 40;

  public function getInverseEdgeConstant() {
    return PhabricatorObjectUsesCredentialsEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
