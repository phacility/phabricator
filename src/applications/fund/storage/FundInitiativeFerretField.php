<?php

final class FundInitiativeFerretField
  extends PhabricatorFerretField {

  public function getApplicationName() {
    return 'fund';
  }

  public function getIndexKey() {
    return 'initiative';
  }

}
