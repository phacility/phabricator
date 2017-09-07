<?php

final class PhabricatorUserFerretDocument
  extends PhabricatorFerretDocument {

  public function getApplicationName() {
    return 'user';
  }

  public function getIndexKey() {
    return 'user';
  }

}
