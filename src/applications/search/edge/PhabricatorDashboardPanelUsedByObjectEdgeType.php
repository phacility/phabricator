<?php

final class PhabricatorDashboardPanelUsedByObjectEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 72;

  public function getInverseEdgeConstant() {
    return PhabricatorObjectUsesDashboardPanelEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
