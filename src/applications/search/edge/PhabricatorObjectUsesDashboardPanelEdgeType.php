<?php

final class PhabricatorObjectUsesDashboardPanelEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 71;

  public function getInverseEdgeConstant() {
    return PhabricatorDashboardPanelUsedByObjectEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
