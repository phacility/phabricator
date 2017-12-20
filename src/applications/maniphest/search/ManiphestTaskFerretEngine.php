<?php

final class ManiphestTaskFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'maniphest';
  }

  public function getScopeName() {
    return 'task';
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
