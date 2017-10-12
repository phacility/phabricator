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

  public function getConduitKey() {
    return 'task.duplicate';
  }

  public function getConduitName() {
    return pht('Closed as Duplicate');
  }

  public function getConduitDescription() {
    return pht(
      'The source task has been closed as a duplicate of the '.
      'destination task.');
  }


}
