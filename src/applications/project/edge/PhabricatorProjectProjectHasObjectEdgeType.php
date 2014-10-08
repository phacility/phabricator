<?php

final class PhabricatorProjectProjectHasObjectEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 42;

  public function getInverseEdgeConstant() {
    return PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
  }

}
