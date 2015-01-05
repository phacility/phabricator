<?php

final class PhabricatorTestNoCycleEdgeType extends PhabricatorEdgeType {

  const EDGECONST = 9000;

  public function shouldPreventCycles() {
    return true;
  }

}
