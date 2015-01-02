<?php

final class PhabricatorAsanaTaskHasObjectEdgeType extends PhabricatorEdgeType {

  const EDGECONST = 80000;

  public function getInverseEdgeConstant() {
    return PhabricatorObjectHasAsanaTaskEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
