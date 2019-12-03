<?php

final class PhortuneAccountHasMerchantEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 73;

  public function getInverseEdgeConstant() {
    return PhortuneMerchantHasAccountEdgeType::EDGECONST;
  }
}
