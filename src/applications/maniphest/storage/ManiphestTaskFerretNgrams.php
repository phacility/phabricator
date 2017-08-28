<?php

final class ManiphestTaskFerretNgrams
  extends PhabricatorFerretNgrams {

  public function getApplicationName() {
    return 'maniphest';
  }

  public function getIndexKey() {
    return 'task';
  }

}
