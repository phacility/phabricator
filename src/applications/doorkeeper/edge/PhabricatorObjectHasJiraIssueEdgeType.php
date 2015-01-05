<?php

final class PhabricatorObjectHasJiraIssueEdgeType extends PhabricatorEdgeType {

  const EDGECONST = 80005;

  public function getInverseEdgeConstant() {
    return PhabricatorJiraIssueHasObjectEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
