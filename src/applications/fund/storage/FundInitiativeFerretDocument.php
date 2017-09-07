<?php

final class FundInitiativeFerretDocument
  extends PhabricatorFerretDocument {

  public function getApplicationName() {
    return 'fund';
  }

  public function getIndexKey() {
    return 'initiative';
  }

}
