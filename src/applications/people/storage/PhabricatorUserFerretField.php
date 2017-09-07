<?php

final class PhabricatorUserFerretField
  extends PhabricatorFerretField {

  public function getApplicationName() {
    return 'user';
  }

  public function getIndexKey() {
    return 'user';
  }

}
