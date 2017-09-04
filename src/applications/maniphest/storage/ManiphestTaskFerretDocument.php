<?php

final class ManiphestTaskFerretDocument
  extends PhabricatorFerretDocument {

  public function getApplicationName() {
    return 'maniphest';
  }

  public function getIndexKey() {
    return 'task';
  }

}
