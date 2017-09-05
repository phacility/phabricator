<?php

final class DifferentialRevisionFerretEngine
  extends PhabricatorFerretEngine {

  public function newNgramsObject() {
    return new DifferentialRevisionFerretNgrams();
  }

  public function newDocumentObject() {
    return new DifferentialRevisionFerretDocument();
  }

  public function newFieldObject() {
    return new DifferentialRevisionFerretField();
  }

  protected function getFunctionMap() {
    $map = parent::getFunctionMap();

    $map['body']['aliases'][] = 'summary';

    return $map;
  }

}
