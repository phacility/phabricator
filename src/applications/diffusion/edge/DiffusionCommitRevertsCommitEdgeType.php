<?php

final class DiffusionCommitRevertsCommitEdgeType extends PhabricatorEdgeType {

  const EDGECONST = 55;

  public function getInverseEdgeConstant() {
    return DiffusionCommitRevertedByCommitEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

  public function shouldPreventCycles() {
    return true;
  }

}
