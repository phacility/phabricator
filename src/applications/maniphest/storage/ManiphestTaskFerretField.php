<?php

final class ManiphestTaskFerretField
  extends PhabricatorFerretField {

  public function getApplicationName() {
    return 'maniphest';
  }

  public function getIndexKey() {
    return 'task';
  }

}
