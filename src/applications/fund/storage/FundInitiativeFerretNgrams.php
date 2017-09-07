<?php

final class FundInitiativeFerretNgrams
  extends PhabricatorFerretNgrams {

  public function getApplicationName() {
    return 'fund';
  }

  public function getIndexKey() {
    return 'initiative';
  }

}
