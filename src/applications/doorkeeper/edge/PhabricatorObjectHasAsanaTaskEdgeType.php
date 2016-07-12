<?php

final class PhabricatorObjectHasAsanaTaskEdgeType extends PhabricatorEdgeType {

  const EDGECONST = 80001;

  public function getInverseEdgeConstant() {
    return PhabricatorAsanaTaskHasObjectEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
