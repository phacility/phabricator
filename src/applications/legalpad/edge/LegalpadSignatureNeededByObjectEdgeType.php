<?php

final class LegalpadSignatureNeededByObjectEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 50;

  public function getInverseEdgeConstant() {
    return LegalpadObjectNeedsSignatureEdgeType::EDGECONST;
  }

}
