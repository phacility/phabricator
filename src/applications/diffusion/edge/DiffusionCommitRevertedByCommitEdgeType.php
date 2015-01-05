<?php

final class DiffusionCommitRevertedByCommitEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 56;

  public function getInverseEdgeConstant() {
    return DiffusionCommitRevertsCommitEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
