<?php

final class PhabricatorAsanaSubtaskHasObjectEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 80002;

  public function getInverseEdgeConstant() {
    return PhabricatorObjectHasAsanaSubtaskEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
