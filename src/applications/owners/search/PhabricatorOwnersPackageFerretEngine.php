<?php

final class PhabricatorOwnersPackageFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'owners';
  }

  public function getScopeName() {
    return 'package';
  }

  public function newSearchEngine() {
    return new PhabricatorOwnersPackageSearchEngine();
  }

}
