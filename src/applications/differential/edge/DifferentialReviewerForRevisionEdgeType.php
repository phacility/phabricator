<?php

final class DifferentialReviewerForRevisionEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 36;

  public function getInverseEdgeConstant() {
    return DifferentialRevisionHasReviewerEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
