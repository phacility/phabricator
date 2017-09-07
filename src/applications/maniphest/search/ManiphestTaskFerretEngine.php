<?php

final class ManiphestTaskFerretEngine
  extends PhabricatorFerretEngine {

  public function newNgramsObject() {
    return new ManiphestTaskFerretNgrams();
  }

  public function newDocumentObject() {
    return new ManiphestTaskFerretDocument();
  }

  public function newFieldObject() {
    return new ManiphestTaskFerretField();
  }

  public function newSearchEngine() {
    return new ManiphestTaskSearchEngine();
  }

  protected function getFunctionMap() {
    $map = parent::getFunctionMap();

    $map['body']['aliases'][] = 'desc';
    $map['body']['aliases'][] = 'description';

    return $map;
  }

}
