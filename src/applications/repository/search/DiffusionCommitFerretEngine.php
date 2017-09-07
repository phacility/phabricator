<?php

final class DiffusionCommitFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'repository';
  }

  public function getScopeName() {
    return 'commit';
  }

  public function newSearchEngine() {
    return new PhabricatorCommitSearchEngine();
  }

}
