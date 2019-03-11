<?php

final class PhabricatorProjectColumnNaturalOrder
  extends PhabricatorProjectColumnOrder {

  const ORDERKEY = 'natural';

  public function getDisplayName() {
    return pht('Natural');
  }

  public function getHasHeaders() {
    return false;
  }

  public function getCanReorder() {
    return true;
  }

  public function getMenuOrder() {
    return 0;
  }

}
