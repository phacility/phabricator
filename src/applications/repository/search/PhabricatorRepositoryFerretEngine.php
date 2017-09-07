<?php

final class PhabricatorRepositoryFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'repository';
  }

  public function getScopeName() {
    return 'repository';
  }

  public function newSearchEngine() {
    return new PhabricatorRepositorySearchEngine();
  }

}
