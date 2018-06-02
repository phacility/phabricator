<?php

final class PhabricatorRepositoryIdentityFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'repository';
  }

  public function getScopeName() {
    return 'identity';
  }

  public function newSearchEngine() {
    return new DiffusionRepositoryIdentitySearchEngine();
  }

}
