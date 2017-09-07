<?php

final class PhabricatorUserFerretNgrams
  extends PhabricatorFerretNgrams {

  public function getApplicationName() {
    return 'user';
  }

  public function getIndexKey() {
    return 'user';
  }

}
