<?php

final class PhabricatorObjectMentionsObjectEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 52;

  public function getInverseEdgeConstant() {
    return PhabricatorObjectMentionedByObjectEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

  public function getConduitKey() {
    return 'mention';
  }

  public function getConduitName() {
    return pht('Mention');
  }

  public function getConduitDescription() {
    return pht(
      'The source object has a comment which mentions the destination object.');
  }

}
