<?php

final class DifferentialRevisionFerretDocument
  extends PhabricatorFerretDocument {

  public function getApplicationName() {
    return 'differential';
  }

  public function getIndexKey() {
    return 'revision';
  }

}
