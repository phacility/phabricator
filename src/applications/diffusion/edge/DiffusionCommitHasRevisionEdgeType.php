<?php

final class DiffusionCommitHasRevisionEdgeType extends PhabricatorEdgeType {

  const EDGECONST = 32;

  public function getInverseEdgeConstant() {
    return DifferentialRevisionHasCommitEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

  public function getConduitKey() {
    return 'commit.revision';
  }

  public function getConduitName() {
    return pht('Commit Has Revision');
  }

  public function getConduitDescription() {
    return pht(
      'The source commit is associated with the destination revision.');
  }

}
