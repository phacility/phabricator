<?php

final class DifferentialRevisionFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'differential';
  }

  public function getScopeName() {
    return 'revision';
  }

  public function newSearchEngine() {
    return new DifferentialRevisionSearchEngine();
  }

  protected function getFunctionMap() {
    $map = parent::getFunctionMap();

    $map['body']['aliases'][] = 'summary';

    return $map;
  }

}
