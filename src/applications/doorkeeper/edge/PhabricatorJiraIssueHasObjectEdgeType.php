<?php

final class PhabricatorJiraIssueHasObjectEdgeType extends PhabricatorEdgeType {

  const EDGECONST = 80004;

  public function getInverseEdgeConstant() {
    return PhabricatorObjectHasJiraIssueEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

}
