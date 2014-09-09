<?php

final class PhabricatorObjectMentionsObject extends PhabricatorEdgeType {

  const EDGECONST = 52;

  public function getInverseEdgeConstant() {
    return PhabricatorObjectMentionedByObject::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
