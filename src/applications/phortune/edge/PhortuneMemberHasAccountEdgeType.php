<?php

final class PhortuneMemberHasAccountEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 28;

  public function getInverseEdgeConstant() {
    return PhortuneAccountHasMemberEdgeType::EDGECONST;
  }

}
