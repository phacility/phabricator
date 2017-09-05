<?php

final class DifferentialRevisionFerretNgrams
  extends PhabricatorFerretNgrams {

  public function getApplicationName() {
    return 'differential';
  }

  public function getIndexKey() {
    return 'revision';
  }

}
