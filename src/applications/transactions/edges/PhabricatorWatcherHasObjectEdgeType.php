<?php

final class PhabricatorWatcherHasObjectEdgeType extends PhabricatorEdgeType {

  const EDGECONST = 48;

  public function getInverseEdgeConstant() {
    return PhabricatorObjectHasWatcherEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
