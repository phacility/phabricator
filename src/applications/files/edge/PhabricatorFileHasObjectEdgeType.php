<?php

final class PhabricatorFileHasObjectEdgeType extends PhabricatorEdgeType {

  const EDGECONST = 26;

  public function getInverseEdgeConstant() {
    return PhabricatorObjectHasFileEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
