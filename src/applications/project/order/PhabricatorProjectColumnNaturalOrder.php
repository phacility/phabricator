<?php

final class PhabricatorProjectColumnNaturalOrder
  extends PhabricatorProjectColumnOrder {

  const ORDERKEY = 'natural';

  public function getDisplayName() {
    return pht('Natural');
  }

}
