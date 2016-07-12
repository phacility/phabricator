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

}
