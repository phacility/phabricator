<?php

final class PhabricatorRoleRoleHasObjectEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 659;

  public function getInverseEdgeConstant() {
    return PhabricatorRoleObjectHasRoleEdgeType::EDGECONST;
  }

}
