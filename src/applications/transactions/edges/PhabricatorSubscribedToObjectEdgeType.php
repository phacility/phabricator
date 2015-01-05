<?php

final class PhabricatorSubscribedToObjectEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 22;

  public function getInverseEdgeConstant() {
    return PhabricatorObjectHasSubscriberEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
