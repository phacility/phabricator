<?php

final class DiffusionCommitHasRevisionEdgeType extends PhabricatorEdgeType {

  const EDGECONST = 32;

  public function getInverseEdgeConstant() {
    return DifferentialRevisionHasCommitEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
