<?php

final class DifferentialRevisionFerretField
  extends PhabricatorFerretField {

  public function getApplicationName() {
    return 'differential';
  }

  public function getIndexKey() {
    return 'revision';
  }

}
