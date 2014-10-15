<?php

final class PhortuneMemberHasMerchantEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 54;

  public function getInverseEdgeConstant() {
    return PhortuneMerchantHasMemberEdgeType::EDGECONST;
  }

}
