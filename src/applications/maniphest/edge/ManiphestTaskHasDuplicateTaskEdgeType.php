<?php

final class ManiphestTaskHasDuplicateTaskEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 62;

  public function getInverseEdgeConstant() {
    return ManiphestTaskIsDuplicateOfTaskEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
