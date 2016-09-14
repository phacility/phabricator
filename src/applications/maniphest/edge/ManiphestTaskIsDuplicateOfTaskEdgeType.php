<?php

final class ManiphestTaskIsDuplicateOfTaskEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 63;

  public function getInverseEdgeConstant() {
    return ManiphestTaskHasDuplicateTaskEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
