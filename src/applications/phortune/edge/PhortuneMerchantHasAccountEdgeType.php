<?php

final class PhortuneMerchantHasAccountEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 74;

  public function getInverseEdgeConstant() {
    return PhortuneAccountHasMerchantEdgeType::EDGECONST;
  }

}
