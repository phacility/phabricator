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

  public function getConduitKey() {
    return 'task.merged-in';
  }

  public function getConduitName() {
    return pht('Merged In');
  }

  public function getConduitDescription() {
    return pht(
      'The source task has had the destination task closed as a '.
      'duplicate and merged into it.');
  }

}
