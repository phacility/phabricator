<?php

final class PhabricatorProjectFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'project';
  }

  public function getScopeName() {
    return 'project';
  }

  public function newSearchEngine() {
    return new PhabricatorProjectSearchEngine();
  }

}
