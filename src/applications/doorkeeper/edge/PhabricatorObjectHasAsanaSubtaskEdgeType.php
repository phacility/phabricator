<?php

final class PhabricatorObjectHasAsanaSubtaskEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 80003;

  public function getInverseEdgeConstant() {
    return PhabricatorAsanaSubtaskHasObjectEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
