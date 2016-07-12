<?php

final class PhabricatorContributedToObjectEdgeType extends PhabricatorEdgeType {

  const EDGECONST = 34;

  public function getInverseEdgeConstant() {
    return PhabricatorObjectHasContributorEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
